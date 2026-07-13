# Archived AWS Infrastructure

AWS infrastructure is currently paused to avoid running costs.

The last active CDK implementation is saved in:

- `docs/archived-infra/guide-stack-active-2026-07-13.ts`

To resume the previous architecture, review the retained AWS resources first,
then copy the archived stack implementation back to `infra/lib/guide-stack.ts`
and set `AWS_DEPLOY_PAUSED` in `.github/workflows/deploy.yml` to `false`.

Retained resources may include the stopped RDS instance, ECR repository, S3
buckets, APP_KEY secret, and the VPC pieces required by the retained RDS
instance.
