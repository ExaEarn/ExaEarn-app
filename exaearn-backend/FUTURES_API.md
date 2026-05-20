# ExaEarn Futures API Documentation

## Overview
The Futures API provides endpoints for trading futures contracts on ExaEarn. All endpoints require authentication (`Bearer token`) and 2FA verification.

---

## Authentication
All requests must include:
```
Authorization: Bearer {token}
```

All futures endpoints require 2FA to be verified and are rate-limited to 120 requests per minute.

---

## Endpoints

### 1. Get Markets
**GET** `/api/futures/markets`

Get all active futures markets.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "symbol": "BTCUSD",
      "status": "active",
      "last_price": "45000.00000000",
      "min_leverage": 1,
      "max_leverage": 100,
      "created_at": "2026-04-28T10:00:00Z"
    }
  ]
}
```

---

### 2. Validate Order
**POST** `/api/futures/orders/validate`

Pre-flight validation before placing an order. Returns order details and required margin without executing.

**Request:**
```json
{
  "symbol": "BTCUSD",
  "type": "market",
  "side": "long",
  "quantity": "1",
  "leverage": 10,
  "price": null
}
```

**Response (Valid):**
```json
{
  "data": {
    "can_place": true,
    "errors": [],
    "data": {
      "symbol": "BTCUSD",
      "execution_price": "45000",
      "quantity": "1",
      "leverage": 10,
      "notional_value": "45000",
      "margin_required": "4500"
    }
  }
}
```

**Response (Invalid):**
```json
{
  "data": {
    "can_place": false,
    "errors": [
      "Leverage must be between 1 and 100."
    ],
    "data": null
  }
}
```

---

### 3. Place Order
**POST** `/api/futures/orders`

Place a new futures order (market or limit).

**Request:**
```json
{
  "symbol": "BTCUSD",
  "type": "market",
  "side": "long",
  "quantity": "1",
  "leverage": 10,
  "price": null,
  "metadata": {
    "source": "mobile"
  }
}
```

**Parameters:**
- `symbol` (string, required): Market symbol (e.g., BTCUSD, ETHUSD)
- `type` (string, required): Order type - `market` or `limit`
- `side` (string, required): Position direction - `long` or `short`
- `quantity` (decimal, required): Order quantity (must be > 0)
- `leverage` (integer, required): Leverage multiplier (1-100)
- `price` (decimal, optional): Limit price (required if type is `limit`)
- `metadata` (object, optional): Custom metadata

**Response (Success - 201):**
```json
{
  "data": {
    "id": 1,
    "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "user_id": 1,
    "futures_market_id": 1,
    "symbol": "BTCUSD",
    "type": "market",
    "side": "long",
    "price": null,
    "quantity": "1.00000000",
    "leverage": 10,
    "notional_value": "45000.00000000",
    "initial_margin": "4500.00000000",
    "filled_quantity": "0.00000000",
    "remaining_quantity": "1.00000000",
    "status": "open",
    "source": "api",
    "metadata": null,
    "created_at": "2026-04-28T10:30:00Z",
    "updated_at": "2026-04-28T10:30:00Z"
  }
}
```

**Response (Error - 422):**
```json
{
  "message": "Insufficient futures margin balance."
}
```

---

### 4. Get Order Details
**GET** `/api/futures/orders/{orderUuid}`

Retrieve full details of a specific order.

**Response:**
```json
{
  "data": {
    "id": 1,
    "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "user_id": 1,
    "futures_market_id": 1,
    "symbol": "BTCUSD",
    "type": "market",
    "side": "long",
    "price": null,
    "quantity": "1.00000000",
    "leverage": 10,
    "notional_value": "45000.00000000",
    "initial_margin": "4500.00000000",
    "filled_quantity": "1.00000000",
    "remaining_quantity": "0.00000000",
    "status": "filled",
    "source": "api",
    "metadata": null,
    "market": {
      "id": 1,
      "symbol": "BTCUSD",
      "status": "active",
      "last_price": "45050.00000000"
    },
    "user": {
      "id": 1,
      "email": "user@example.com"
    },
    "created_at": "2026-04-28T10:30:00Z",
    "updated_at": "2026-04-28T10:31:00Z"
  }
}
```

---

### 5. Cancel Order
**DELETE** `/api/futures/orders/{orderUuid}`

Cancel an open or partially-filled order and release locked margin.

**Response:**
```json
{
  "data": {
    "id": 1,
    "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "cancelled",
    "created_at": "2026-04-28T10:30:00Z",
    "updated_at": "2026-04-28T10:35:00Z"
  }
}
```

**Error (422):**
```json
{
  "message": "Only open futures orders can be cancelled."
}
```

---

### 6. Batch Cancel Orders
**POST** `/api/futures/orders/batch-cancel`

Cancel multiple orders in a single request.

**Request:**
```json
{
  "order_uuids": [
    "550e8400-e29b-41d4-a716-446655440000",
    "550e8400-e29b-41d4-a716-446655440001",
    "550e8400-e29b-41d4-a716-446655440002"
  ]
}
```

**Response:**
```json
{
  "data": {
    "cancelled": [
      {
        "id": 1,
        "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "status": "cancelled"
      },
      {
        "id": 2,
        "order_uuid": "550e8400-e29b-41d4-a716-446655440001",
        "status": "cancelled"
      }
    ],
    "failed": [
      {
        "order_uuid": "550e8400-e29b-41d4-a716-446655440002",
        "error": "Order not found."
      }
    ]
  }
}
```

---

### 7. Get Margin Status
**GET** `/api/futures/margin/status`

Get user's current margin usage, available margin, and locked margin.

**Response:**
```json
{
  "data": {
    "total_margin": "10000.00000000",
    "available_margin": "5500.00000000",
    "locked_margin": "4500.00000000",
    "margin_usage_percentage": "45.00"
  }
}
```

---

### 8. Get Open Orders
**GET** `/api/futures/orders/open?symbol=BTCUSD&per_page=50`

Retrieve user's open and partially-filled orders.

**Query Parameters:**
- `symbol` (string, optional): Filter by market symbol
- `per_page` (integer, optional): Results per page (default: 50)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
      "symbol": "BTCUSD",
      "type": "limit",
      "side": "long",
      "price": "44000",
      "quantity": "1",
      "leverage": 10,
      "status": "open",
      "created_at": "2026-04-28T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 5,
    "per_page": 50,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### 9. Get Open Positions
**GET** `/api/futures/positions?symbol=BTCUSD&per_page=50`

Retrieve user's open futures positions.

**Query Parameters:**
- `symbol` (string, optional): Filter by market symbol
- `per_page` (integer, optional): Results per page (default: 50)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "symbol": "BTCUSD",
      "side": "long",
      "quantity": "1.00000000",
      "entry_price": "45000.00000000",
      "margin": "4500.00000000",
      "leverage": 10,
      "unrealized_pnl": "500.00000000",
      "status": "open",
      "opened_at": "2026-04-28T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 2,
    "per_page": 50,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### 10. Get Recent Trades
**GET** `/api/futures/trades?symbol=BTCUSD&limit=100`

Retrieve recent trades on the futures market.

**Query Parameters:**
- `symbol` (string, optional): Filter by market symbol
- `limit` (integer, optional): Max results (default: 100, max: 1000)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "symbol": "BTCUSD",
      "price": "45000.00000000",
      "quantity": "1.00000000",
      "notional_value": "45000.00000000",
      "executed_at": "2026-04-28T10:31:00Z"
    }
  ]
}
```

---

## Error Handling

### Common Error Responses

**Insufficient Margin (422):**
```json
{
  "message": "Insufficient futures margin balance."
}
```

**Invalid Leverage (422):**
```json
{
  "message": "Leverage out of allowed range."
}
```

**Market Not Active (422):**
```json
{
  "message": "Futures market is not active."
}
```

**Order Not Found (404):**
```json
{
  "message": "Order not found."
}
```

**Rate Limited (429):**
```json
{
  "message": "Too Many Requests"
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated"
}
```

**2FA Required (403):**
```json
{
  "message": "2FA verification required"
}
```

---

## Risk Management Rules

The system enforces several risk rules to prevent liquidation:

1. **Leverage Limits**: Between min (1) and max (100)
2. **Position Notional Cap**: Maximum notional value per position
3. **Maintenance Margin Buffer**: Minimum margin requirement
4. **Order Rate Limit**: Maximum orders per minute
5. **Abnormal Pattern Detection**: Detects high-frequency activity

All validation is performed before order placement and will return a 422 error if any rule is violated.

---

## Margin Management

Margin is automatically managed by the system:

- **Locking**: When an order is placed, required margin is locked
- **Release**: When an order is cancelled or filled, margin is released or converted to maintenance margin
- **Liquidation**: Positions are liquidated if maintenance margin falls below threshold

---

## Real-Time Updates

Market updates are published via Redis Pub/Sub on the `futures_updates` channel:

```json
{
  "event": "futures.order.placed",
  "data": {
    "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "symbol": "BTCUSD",
    "status": "open"
  },
  "timestamp": "2026-04-28T10:30:00Z"
}
```

---

## Rate Limiting

- Futures endpoints: **120 requests per minute**
- Global limit: **60 requests per minute** (other endpoints)

Headers indicate remaining quota:
```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1619606700
```

---

## Testing

Run the test suite:

```bash
php artisan test tests/Unit/FuturesOrderServiceTest.php
php artisan test tests/Feature/FuturesControllerTest.php
```

---

## Support

For issues or questions, contact: `support@exaearn.com`
