# ExaEarn NFT Financial Flow

Fixed-price purchases use the canonical ledger:

Buyer funding account
-> canonical `nft_purchase` reservation
-> seller payable
-> marketplace fee revenue
-> royalty payable
-> optional network fee expense

No wallet balance columns are directly mutated by the NFT marketplace service.

The sale reference is deterministic from buyer, listing and idempotency key. Replayed purchases return the original sale and do not duplicate reservations, ledger entries or ownership transfers.

Auction bids use canonical `nft_bid` reservations. When a higher bid arrives, the previous highest bid reservation is released and the new bidder's reservation remains active until auction finalization or cancellation policy consumes/releases it.
