# ExaEarn Crowdfunding Documents

Crowdfunding documents are stored in `crowdfunding_documents` with a configured storage disk. Public campaign media and disclosures are distinct from private creator/compliance documents.

## Access Policy

- Public approved media/disclosures may be listed on public campaign details.
- Private documents may be accessed only by the owner or authorized admins.
- File access is audited through `activity_logs`.
- Private campaign documents default to `PENDING_REVIEW`.
- Public campaign media defaults to `APPROVED` only for allowed public document types.

## Review

Admins with review permission may approve, reject, require replacement or expire a document. Review actions notify the document owner and do not mutate pledge, escrow or ledger state.
