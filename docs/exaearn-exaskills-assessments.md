# ExaEarn ExaSkills Assessments

Assessments reuse `quizzes`, `quiz_questions` and `quiz_attempts`.

Server-side rules:

- client answers are submitted, but server calculates score
- idempotency key prevents duplicate attempt effects
- max attempts are enforced
- historical attempts store assessment version metadata
- course completion checks assessment pass state where a quiz exists

Client-side scores are never authoritative.
