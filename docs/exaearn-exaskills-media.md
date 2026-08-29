# ExaEarn ExaSkills Media

Media is represented by `skills_media_assets`.

## Supported Concepts

- Course thumbnails/covers
- Lesson videos/documents/attachments
- Instructor evidence
- Challenge evidence

The current adapter stores files through Laravel disks and records provider metadata. Production video/object storage is configuration-driven and remains operational setup until real provider credentials exist.

## Security

Private media is not exposed as a raw permanent public URL. Access is checked server-side for owners, enrolled learners and admins.
