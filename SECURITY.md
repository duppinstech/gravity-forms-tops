# Security policy

## Supported versions

Security fixes are applied to the latest release on the default branch. Use the newest tagged version where possible.

## Reporting a vulnerability

**Please do not open a public GitHub issue for undisclosed security problems.**

Instead, contact the maintainers privately:

1. Use [GitHub Security Advisories](https://github.com/duppinstech/gravity-forms-tops/security/advisories/new) if enabled for this repository, **or**
2. Email the repository owner with a clear subject line (for example: `Security: gravity-forms-tops`).

Include:

- A short description of the issue and its impact
- Steps to reproduce (proof-of-concept if safe to share)
- Affected versions or commit, if known

We aim to acknowledge reports within a few business days.

## Sensitive data in this plugin

- TowX **password** (`tops_password`) and **authentication key** (`tops_auth_key`) are stored in Gravity Forms **form meta** (same database as your WordPress site). Current releases encrypt **only these two fields** **at rest** using Gravity Forms’ built-in crypto (**AES-256-CTR** with **HMAC-SHA-512**, keyed from WordPress salts via `GFCommon::openssl_encrypt`). **TowX User ID** (`tops_user_id`) and **Session ID** (`tops_session_id`) live in the same form meta but are **not encrypted** by this plugin—they remain readable if someone can read the database.
- Anyone who can read both your **database** and **`wp-config.php`** (or equivalent secrets) can still decrypt the encrypted credential fields—this is the usual WordPress trade-off for reversible secrets. Restrict **form administrator** access, use **HTTPS** for wp-admin, and follow your host’s database security practices.
- Since **1.4.0**, any plaintext password/key left by releases before 1.3.5 is encrypted automatically when the plugin upgrades; saving a form’s TOPS settings also encrypts them.
- Secrets are decrypted only in memory when calling TowX. The settings screen receives the stored (encrypted) value, never the plaintext.
- Gravity Forms may embed the full form object (including add-on settings) in **admin JavaScript** on the form settings screen. Password and key appear there only as ciphertext; User ID and Session ID appear as stored. Mitigate with strict admin access and auditing.
- **Request log** rows (`{prefix}gf_tops_request_log`) store the Create Call XML with User ID, Password, Session ID and Authentication key replaced by `***` (User ID since 1.5.0), plus TowX’s raw response. They still contain customer details from the submission (name, phone, addresses, vehicle, notes) and are kept until erased with **Tools → Erase Personal Data** or the plugin is deleted. Viewing the log requires the Gravity Forms view-entries permission; resending requires edit-entries.
- **Failure emails** (optional, Forms → Settings → TOPS) contain the redacted request XML and TowX’s response. Before **1.4.0** these emails included the TowX password, Session ID and authentication key in plain text; rotate those credentials if error emails were enabled on an older version.
- Cached TowX vehicle lists are stored as transients keyed by a salted hash of the credentials; the credentials themselves are not stored in the cache.

## Out of scope

- Issues in **Gravity Forms** core or other third-party plugins (report to the respective vendor)
- TowX / TowXchange API availability or certificate configuration on their infrastructure
- Brute-force attacks mitigated by WordPress authentication and server-level protections
