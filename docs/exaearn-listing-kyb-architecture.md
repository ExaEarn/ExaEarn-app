# ExaEarn Listing Organization KYB Architecture

Date: 2026-09-01

## Control boundaries

The Listing Portal reuses canonical ExaEarn user authentication. User identity, organization KYB, and asset listing due diligence are separate controls. An authenticated user may create or join an organization, but cannot submit an asset listing until that organization reaches `APPROVED` KYB status.

## Domain model

- `listing_organizations` owns the KYB state and progress projection.
- `listing_organization_people` stores directors, beneficial owners, and the authorized representative.
- `listing_organization_documents` stores private, versioned document metadata and checksums. Storage paths are never serialized.
- `listing_organization_declarations` records policy-versioned attestations with request evidence hashes.
- `listing_organization_invitations` uses expiring, recipient-bound, hashed tokens.
- `listing_organization_kyb_reviews` separates applicant-visible decisions from private reviewer notes and manual verification evidence.

Organization membership continues to use the existing Listing governance model. Ordinary membership updates cannot replace the owner; ownership transfer is a distinct recent-authenticated operation.

## Workflow

The applicant workflow has ten stages: organization basics, legal entity, business information, directors, beneficial ownership, authorized representative, regulatory information, documents, declarations, and review. Progress is calculated from persisted records rather than client state.

The explicit KYB lifecycle is:

```text
DRAFT -> SUBMITTED -> UNDER_REVIEW -> APPROVED
                              |-> INFORMATION_REQUESTED -> SUBMITTED
                              |-> REJECTED
APPROVED -> REVERIFICATION_REQUIRED -> SUBMITTED
```

Applicant mutation is blocked while a submission is under controlled review. Administrative decisions are transition-validated and audited.

## Security

- Every organization operation verifies active membership and role.
- Cross-organization identifiers fail closed.
- Representative records link to the authenticated canonical user.
- Beneficial ownership percentages cannot exceed 100 percent.
- Documents use private storage, MIME/size validation, checksums, and versions.
- Invitation secrets are stored only as hashes and never returned by APIs.
- Listing submission is independently gated on approved organization KYB.

## Provider boundaries

Company-registry verification, sanctions/PEP/adverse-media screening, and malware scanning are adapters controlled by configuration. They report `NOT_CONNECTED` until approved providers are configured. The software does not manufacture a clear result. Manual approval while providers are disconnected requires reviewer evidence.

