# Customer Registration & Credit Application API

> ⚠️ **Disclaimer**:  
> This repository is a demo presentation of my solo developed REST API at F.W. Webb.
> It is **not intended for direct deployment** — certain internal classes and URLs have been removed or stubbed.  
> Some components (ERP/CRM integrations, internal OAuth, etc.) are specific to F.W. Webb infrastructure.  

## Overview

This project is a **REST API server** built in PHP (Apache, MariaDB) to handle:

- **Customer Registration**
  - Integrates with **Jotform** webhooks for new customer signups
  - Registers customer into multiple backend systems: ERP, CRM, e-commerce site
  - Sends email notifications via **PHPMailer**

- **Credit Applications & Tax Exempt Forms**
  - Integrates with **DocuSign** APIs (`docusign/esign-client`)
  - Supports embedded signing and responsive (email) signing
  - Uses both **JWT OAuth** and **Auth Code Grant OAuth2.0** flows for credit department workflows
  - Routes application approvals to internal credit team

- **Webhooks**
  - Jotform webhook handler with request validation (hidden request values + HMAC auth)
  - DocuSign webhook handler for envelope lifecycle events (`envelope-completed`, `recipient-completed`, etc.)
  - Includes retry handling as per DocuSign Connect best practices

- **Scheduled Jobs**
  - Nightly cron processes customer form progress vs. submissions
  - Handles “not submitted” edge cases to avoid premature reporting

- **Stockpile Express Additions**
  - Parallel flow for Stockpile Express customers (branch 125)
  - Branded emails, logos, and DocuSign workflow variations
  - Shared webhook with branch-based routing

## Architecture

- **Stack**: PHP 8.x, Apache, MariaDB  
- **Libraries**:  
  - [`docusign/esign-client`](https://github.com/docusign/docusign-php-client) for envelope management  
  - [`phpmailer/phpmailer`](https://github.com/PHPMailer/PHPMailer) for notifications  

## Key Directories
```text
/api       # Redirect endpoints (registration, docusign, uploads)
/classes   # Core classes (Docusign, Email, Curl, ApiRequest, etc.)
/webhooks  # Webhook endpoints (jotform, docusign)
/templates # Base PDF forms + JSON "tabs" mappings
/cron      # Nightly cron jobs
/includes  # Helper classes (spx_* for Stockpile Express)
```

## Security & Auth

- **All inbound requests** flow through a DMZ endpoint server before reaching this API  
- **HMAC authentication** secures inbound requests from DMZ → API  
- **Hidden request values** secure Jotform webhook validation  
- **OAuth 2.0** and **JWT tokens** secure Docusign API access

## Configuration

Environment variables (`.env` example):

```text
APP_ENV=development
DB_HOST=127.0.0.1
DB_USER=appuser
DB_PASS=changeme
DB_NAME=appdb

DOCUSIGN_CLIENT_ID=xxxx
DOCUSIGN_SECRET=xxxx
DOCUSIGN_ACCOUNT_ID=xxxx

INTERNAL_MODE=false
```

## Disclaimer

This codebase demonstrates:
- Architecture of a webhook-driven API service
- Integration with 3rd-party services (Jotform, Docusign)
- Secure relaying through DMZ endpoints
- Handling of asynchronous workflows (webhooks, cron)

Certain files (ERP/CRM integration, internal URLs, production secrets) have been **removed or stubbed** for security.

