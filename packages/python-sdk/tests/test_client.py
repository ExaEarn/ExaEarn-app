import hashlib
import hmac
import json
import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).parents[1] / "src"))

from exaearn import ExaEarnApiError, ExaEarnClient, ExaEarnTransportError
from exaearn.client import build_canonical_request, sign_canonical_request


class FakeTransport:
    def __init__(self, responses):
        self.responses = list(responses)
        self.calls = []

    def __call__(self, method, url, headers, body, timeout):
        self.calls.append((method, url, headers, body, timeout))
        response = self.responses.pop(0)
        if isinstance(response, Exception):
            raise response
        status, headers_out, payload = response
        return status, headers_out, json.dumps(payload).encode()


class ClientTest(unittest.TestCase):
    def test_public_time_and_environment_are_explicit(self):
        transport = FakeTransport([(200, {}, {"success": True, "data": {"unix_milliseconds": 123}})])
        client = ExaEarnClient(environment="sandbox", transport=transport)
        self.assertEqual(123, client.get_server_time()["unix_milliseconds"])
        self.assertTrue(transport.calls[0][1].startswith("https://sandbox-api.exaearn.com/"))

    def test_signed_spot_order_uses_canonical_hmac_and_redacted_repr(self):
        transport = FakeTransport([(200, {}, {"success": True, "data": {"order_id": "o-1"}})])
        client = ExaEarnClient(api_key="key-secret-value", api_secret="super-secret", transport=transport)
        self.assertEqual("o-1", client.create_spot_order({"symbol": "BTC-USDT", "quantity": "0.1"})["order_id"])
        method, url, headers, body, _ = transport.calls[0]
        target = url.removeprefix("https://sandbox-api.exaearn.com")
        path, _, query = target.partition("?")
        canonical = build_canonical_request(method, path, query, headers["EXA-API-TIMESTAMP"], headers["EXA-API-NONCE"], body)
        self.assertEqual(hmac.new(b"super-secret", canonical.encode(), hashlib.sha256).hexdigest(), headers["EXA-API-SIGNATURE"])
        self.assertNotIn("super-secret", repr(client))
        self.assertNotIn("key-secret-value", repr(client))

    def test_shared_cross_language_signing_fixture(self):
        fixture = json.loads((pathlib.Path(__file__).parents[3] / "contracts" / "developer-signing-v1.json").read_text())
        canonical = build_canonical_request(fixture["method"], fixture["path"], fixture["query_input"], fixture["timestamp"], fixture["nonce"], fixture["body"].encode())
        self.assertEqual(fixture["canonical"], canonical)
        self.assertEqual(fixture["signature"], sign_canonical_request(fixture["api_secret"], fixture["method"], fixture["path"], fixture["query_input"], fixture["timestamp"], fixture["nonce"], fixture["body"].encode()))

    def test_api_error_preserves_request_and_rate_metadata(self):
        transport = FakeTransport([(429, {"Retry-After": "3"}, {"success": False, "error": {"code": "RATE_LIMITED", "message": "Slow down", "request_id": "r-1"}})])
        with self.assertRaises(ExaEarnApiError) as raised:
            ExaEarnClient(transport=transport).get_tickers()
        self.assertEqual("r-1", raised.exception.request_id)
        self.assertEqual("3", raised.exception.retry_after)

    def test_get_retries_transport_failure_but_write_does_not(self):
        failure = ExaEarnTransportError("timeout")
        transport = FakeTransport([failure, (200, {}, {"success": True, "data": []})])
        self.assertEqual([], ExaEarnClient(transport=transport).get_tickers())
        self.assertEqual(2, len(transport.calls))
        write_transport = FakeTransport([failure, (200, {}, {"success": True, "data": {}})])
        with self.assertRaises(ExaEarnTransportError):
            ExaEarnClient(api_key="k", api_secret="s", transport=write_transport).create_spot_order({})
        self.assertEqual(1, len(write_transport.calls))

    def test_pagination_follows_cursor(self):
        transport = FakeTransport([
            (200, {}, {"success": True, "data": {"items": [1], "next_cursor": "two"}}),
            (200, {}, {"success": True, "data": {"items": [2], "next_cursor": None}}),
        ])
        client = ExaEarnClient(api_key="k", api_secret="s", transport=transport)
        self.assertEqual([1, 2], list(client.paginate("/api/developer/v1/futures/orders")))
        self.assertIn("cursor=two", transport.calls[1][1])


if __name__ == "__main__":
    unittest.main()
