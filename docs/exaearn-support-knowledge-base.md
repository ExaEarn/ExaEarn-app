# ExaEarn Support Knowledge Base

The Help Center now has CMS primitives:

- `kb_categories`
- `kb_articles`
- `kb_article_versions`

Articles support locale, draft/published/archived status, versioning, category assignment and backend search over title, summary and body.

Public user search:

```text
GET /api/v1/support/knowledge-base?q=...
```

Admin CMS:

```text
GET /api/admin/support/knowledge-base
POST /api/admin/support/knowledge-base
```
