# Credential cleanup and rotation

A GitHub access token was found in a tracked `.env.example` in a developer checkout. Its value was removed locally without displaying or reusing it. Treat that token as compromised: revoke it in GitHub, review recent token use, and replace it only with a newly issued least-privilege credential stored in an approved secret manager. Do not put the replacement in any tracked example or `.env` file.

Credential-bearing values in the staging branch's API gateway, blockchain-service, and reward-service `.env.example` files are blank. Generate independent local/test values outside tracked files. These examples are not credential stores.

Before staging deployment, confirm the exposed GitHub token has been revoked and that any other value copied from the same source has been rotated by its owner. Do not mark the exposure resolved based on local blanking alone: the credential owner must revoke it and review recent activity before staging deployment. This change contains no replacement credential.