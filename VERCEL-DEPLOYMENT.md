# LiteUp deployment preparation for Vercel

This repository is prepared for Vercel's community PHP runtime with TiDB Cloud (MySQL-compatible) and a private Vercel Blob store. It is not deployed yet: the project must be connected to a Vercel account and the cloud services below must be provisioned first.

## Before connecting Vercel

1. Review the project and remove any test accounts, API keys, `.env` files, payment proofs, and private customer data from the source repository. `.env` and `uploads/` are excluded from deployment, but secrets must never be committed.
2. Put the project in a private GitHub repository. This workspace currently has no Git repository or remote configured.
3. Confirm the service integrations used by this app (email, electricity, airtime, data, and payment handling) support your production environment. Do not accept real funds until payment processing and the security review have been completed.

Vercel does not provide an official PHP runtime; this project uses the community-maintained `vercel-php@0.9.0` runtime in `vercel.json`. PHP functions have a read-only deployment filesystem, so sessions, login throttling, and uploaded files have been moved to the configured cloud services. The local development path remains available for XAMPP.

## Provision the Vercel services

1. Import the private repository into Vercel. Keep the detected project root at the repository root and use the PHP runtime configuration already in `vercel.json`.
2. Create a **private** Vercel Blob store and connect it to this project. The Node storage bridge uses Vercel's private Blob SDK and deployment identity.
3. Create a TiDB Cloud database through Vercel Marketplace, or another MySQL-compatible managed database that permits secure connections from Vercel Functions. Use the provider's TLS connection details. Do not point production at XAMPP or localhost.
4. In the Vercel project settings, create these encrypted environment variables for Production, Preview, and Development as appropriate:

   - `LITEUP_DB_HOST`: managed database host
   - `LITEUP_DB_PORT`: provider's TLS port (TiDB commonly uses `4000`)
   - `LITEUP_DB_USER`: managed database user
   - `LITEUP_DB_PASSWORD`: managed database password
   - `LITEUP_DB_NAME`: production database name
   - `LITEUP_DB_TLS`: `true`
   - `LITEUP_DB_SSL_CA_FILE`: optional CA file path, only if the provider requires a CA bundle included with the project
   - `LITEUP_STORAGE_SERVICE_TOKEN`: a long random secret shared only by the PHP function and the Node storage bridge
   - `MAIL_HOST`, `MAIL_PORT`, `MAIL_EMAIL`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`: production mail service settings

`BLOB_READ_WRITE_TOKEN` and `VERCEL_URL` are supplied by Vercel when the store and deployment are configured. Keep the storage bridge endpoint private behind the shared token. If Vercel Deployment Protection blocks the PHP function's internal request to `/api/blob`, set `LITEUP_STORAGE_API_URL` to the deployed site's HTTPS `/api/blob` URL and configure protection so the bridge remains reachable by the application.

5. Provision a **new, empty production database**. Apply `sql/liteup_db.sql` first, then run migrations `sql/001_wallet_consistency.sql` through `sql/007_vercel_state.sql` in order. The base SQL file is a schema-only export; it contains no users, balances, transactions, or payment-proof records. Never load local development records as production data.
6. Review provider networking rules and allow only the required secure Vercel-to-database connections. Do not open the database broadly to the public internet. If your Vercel plan does not offer stable outbound IPs, choose a database connection option that supports Vercel's networking model.
7. Deploy a Preview first. Check the homepage, sign-in, email verification, profile image upload/display, proof upload/download (admin only), and session persistence across requests. Run those checks with test accounts and non-real payment data. Then promote to Production only after reviewing the preview and environment values.

## Platform limits and behavior

- Funding proof uploads are limited to 4 MB on Vercel to fit Vercel Functions' 4.5 MB request/response limit. XAMPP keeps its 5 MB application limit.
- Profile pictures are limited to 2 MB.
- Uploaded files are private Blob objects. Profile images are served through an authenticated PHP endpoint, and funding proofs require admin authorization.
- Database sessions and login rate limits are stored in MySQL tables created by migration 007.
- PHP package dependencies are declared in `composer.json`; the Node Blob bridge dependency is pinned in `package.json`.

## Deployment troubleshooting

- **Database connection fails:** verify the host, database name, username, password, TLS port, and provider network access. Do not disable TLS to work around a connection error.
- **Session/schema error:** verify the base schema and migrations 001–007 completed successfully on the production database.
- **Profile/proof storage error:** verify the private Blob store is connected, `LITEUP_STORAGE_SERVICE_TOKEN` is identical in the Vercel environment, and internal requests to `/api/blob` are not blocked by Deployment Protection.
- **Email does not send:** check the production mail credentials and provider's sender/domain verification.
- **A route returns 404:** check Vercel build logs and confirm `vercel.json`, `api/index.php`, and `api/blob.js` are present in the deployed commit.

## Rollback

Use Vercel's deployment rollback for application code. Database schema changes should be backed up and rolled back separately using the managed database provider's documented process. Do not delete the Blob store or database when rolling back application code.
