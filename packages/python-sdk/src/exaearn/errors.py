class ExaEarnTransportError(RuntimeError):
    """A request could not be completed safely."""


class ExaEarnApiError(RuntimeError):
    def __init__(self, code, message, *, status=None, request_id=None, retry_after=None, details=None):
        super().__init__(f"{code}: {message}")
        self.code = code
        self.status = status
        self.request_id = request_id
        self.retry_after = retry_after
        self.details = details or {}
