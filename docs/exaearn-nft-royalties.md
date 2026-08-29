# ExaEarn NFT Royalties

Collection royalty policy is stored in basis points and capped by `config/nft.php`.

Fixed-price marketplace settlement calculates:

- gross sale price
- ExaEarn marketplace fee
- marketplace-enforced royalty
- seller proceeds

Royalty payable is recorded in canonical ledger accounts. On-chain royalty enforcement is not assumed.

