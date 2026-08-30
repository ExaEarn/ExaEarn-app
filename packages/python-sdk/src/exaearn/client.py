from __future__ import annotations

import hashlib
import hmac
import json
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any, Callable, Iterator

from .errors import ExaEarnApiError, ExaEarnTransportError


Transport = Callable[[str, str, dict[str, str], bytes | None, float], tuple[int, dict[str, str], bytes]]


@dataclass(frozen=True)
class Environment:
    name: str
    base_url: str


ENVIRONMENTS = {
    "sandbox": Environment("sandbox", "https://sandbox-api.exaearn.com"),
    "production": Environment("production", "https://api.exaearn.com"),
}


def canonicalize_query(query: str) -> str:
    pairs = urllib.parse.parse_qsl(query, keep_blank_values=True)
    return urllib.parse.urlencode(sorted(pairs), doseq=True, quote_via=urllib.parse.quote)


def build_canonical_request(method: str, path: str, query: str, timestamp: str, nonce: str, body: bytes | None) -> str:
    body_hash = hashlib.sha256(body or b"").hexdigest()
    normalized_path = "/" + path.lstrip("/")
    return "\n".join([method.upper(), normalized_path, canonicalize_query(query), timestamp, nonce, body_hash])


def sign_canonical_request(secret: str, method: str, path: str, query: str, timestamp: str, nonce: str, body: bytes | None) -> str:
    canonical = build_canonical_request(method, path, query, timestamp, nonce, body)
    return hmac.new(secret.encode(), canonical.encode(), hashlib.sha256).hexdigest()


def _urllib_transport(method: str, url: str, headers: dict[str, str], body: bytes | None, timeout: float):
    request = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, dict(response.headers.items()), response.read()
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers.items()), error.read()
    except (urllib.error.URLError, TimeoutError, OSError) as error:
        raise ExaEarnTransportError("ExaEarn request failed before a response was received") from error


class ExaEarnClient:
    """Synchronous ExaEarn REST client with canonical signing and safe retries."""

    def __init__(self, *, api_key: str | None = None, api_secret: str | None = None,
                 environment: str = "sandbox", base_url: str | None = None,
                 timeout: float = 10.0, max_read_retries: int = 2,
                 transport: Transport | None = None):
        if environment not in ENVIRONMENTS:
            raise ValueError("environment must be sandbox or production")
        self.environment = environment
        self.base_url = (base_url or ENVIRONMENTS[environment].base_url).rstrip("/")
        self._api_key = api_key
        self._api_secret = api_secret
        self.timeout = timeout
        self.max_read_retries = max(0, max_read_retries)
        self._transport = transport or _urllib_transport
        self._clock_offset_ms = 0

    def __repr__(self) -> str:
        return f"ExaEarnClient(environment={self.environment!r}, base_url={self.base_url!r}, credentials={'configured' if self._api_key else 'none'})"

    def sync_time(self) -> int:
        started = int(time.time() * 1000)
        data = self.get_server_time()
        ended = int(time.time() * 1000)
        self._clock_offset_ms = int(data["unix_milliseconds"]) - ((started + ended) // 2)
        return self._clock_offset_ms

    def get_server_time(self) -> dict[str, Any]:
        return self.request("GET", "/api/developer/v1/time")

    def get_tickers(self) -> list[dict[str, Any]]:
        return self.request("GET", "/api/developer/v1/tickers")

    def create_spot_order(self, order: dict[str, Any]) -> dict[str, Any]:
        return self.request("POST", "/api/developer/v1/spot/orders", json_body=order, signed=True)

    def get_spot_order(self, order_id: str) -> dict[str, Any]:
        safe_id = urllib.parse.quote(order_id, safe="")
        return self.request("GET", f"/api/developer/v1/spot/orders/{safe_id}", signed=True)

    def paginate(self, path: str, *, limit: int = 100, cursor: str | None = None) -> Iterator[Any]:
        current = cursor
        while True:
            query = {"limit": str(limit)}
            if current:
                query["cursor"] = current
            payload = self.request("GET", path, query=query, signed=True)
            items = payload.get("items", payload if isinstance(payload, list) else [])
            yield from items
            current = payload.get("next_cursor") if isinstance(payload, dict) else None
            if not current:
                return

    def request(self, method: str, path: str, *, query: dict[str, str] | None = None,
                json_body: dict[str, Any] | None = None, signed: bool = False,
                idempotency_key: str | None = None) -> Any:
        method = method.upper()
        if not path.startswith("/api/developer/v1/"):
            raise ValueError("path must target /api/developer/v1/")
        query_string = urllib.parse.urlencode(sorted((query or {}).items()), quote_via=urllib.parse.quote)
        target = path + (f"?{query_string}" if query_string else "")
        body = json.dumps(json_body, separators=(",", ":"), sort_keys=True).encode() if json_body is not None else None
        headers = {"Accept": "application/json", "User-Agent": "exaearn-python/1.0.0"}
        if body is not None:
            headers["Content-Type"] = "application/json"
        if idempotency_key:
            headers["Idempotency-Key"] = idempotency_key
        if signed:
            if not self._api_key or not self._api_secret:
                raise ValueError("signed request requires api_key and api_secret")
            timestamp = str((int(time.time() * 1000) + self._clock_offset_ms) // 1000)
            nonce = secrets.token_hex(16)
            signature = sign_canonical_request(self._api_secret, method, path, query_string, timestamp, nonce, body)
            headers.update({"EXA-API-KEY": self._api_key, "EXA-API-TIMESTAMP": timestamp,
                            "EXA-API-NONCE": nonce, "EXA-API-SIGNATURE": signature})

        attempts = self.max_read_retries + 1 if method == "GET" else 1
        for attempt in range(attempts):
            try:
                status, response_headers, raw = self._transport(method, self.base_url + target, headers, body, self.timeout)
                break
            except ExaEarnTransportError:
                if attempt + 1 >= attempts:
                    raise
                time.sleep(min(0.1 * (2 ** attempt), 1.0))
        try:
            payload = json.loads(raw.decode() or "{}")
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ExaEarnTransportError("ExaEarn returned an invalid JSON response") from error
        if status >= 400 or payload.get("success") is False:
            detail = payload.get("error", {})
            raise ExaEarnApiError(detail.get("code", "HTTP_ERROR"), detail.get("message", "Request failed"),
                                  status=status, request_id=detail.get("request_id") or response_headers.get("X-Exa-Request-Id"),
                                  retry_after=response_headers.get("Retry-After"), details=detail.get("details"))
        return payload.get("data", payload)
