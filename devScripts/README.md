=====================================
	    DOCUMENTATION / NOTES
=====================================
- this readme is used for developer notes and documentation

*** CHANGING THE FORMS USED ***
- notes about changing input fields or the form's base file
FOR DOCUSIGN FORMS:
    - use docusign's webbapp UI to make changes to input fields
    - download and extract just the 'tabs' array from the json
    - save the base forms in `templates/` directory as `.pdf`
    - save form input fields ('tabs') in `templates/` as `.json`
        - use Docusign SDK to match input field type ("tab type") to Docusign API Object
FOR CUST-REG JOTFORM:
    - change on jotform's webapp UI 
        - Note: there are 2 different forms: 1 form for prod's webhook, 1 for dev's webhook. Also 2 forms on testwww.fwwebb.com 
    - click 'export source code' in jotform then copy it all
    - paste the code into fwwebb's webpage

*** HOW TO TEST ***
- Issue: endpoint-dev isn't externally available so webhooks will timeout
    - Note: all other requests (non-webhook requests) are fine (DMZ server -> endpoint-dev works)
- Fix (how to test):
    1. set webhook urls to a DMZ externally facing system's endpoint (ord-web-uv1, old docusign server if still up, etc.)
    2. setup the temp webhook to relay the request to endpoint-dev's endpoint, simulating the webhook event (simple read request data and cURL)
    3. include header, request data in the relay-to-endpoint-dev requests
    - Note: both test webhooks in devScripts/ have this this logic + optional sql logging
- Example:
    - copy code from includes/classes/ApiRequests.php to bed-api-dmz-prod:/var/www/html/docusign-api/test_jotform_webhook.php to read the request data
    - add code for a simple cURL relay to pass the request data and necessary headers to endpoint-dev
        - can copy 'Curl.php' class if that's easier
    - set webhook url in jotform's 'integration' page to {domain-name}/test_jotform_webhook.php
        - (if testing docusign, set the webhook url in the demo sandbox 'Admin' -> 'Connect')
    - requests now are passed to endpoint-dev w/ same headers + data
    - do the same for docusign's webhook for a full test setup
    - Note: bed-api-dmz-prod will be deactivated in the future but just use any endpoint in the dmz

*** 7/30/2025 UPDATE ***
- codebase moved out of the DMZ
- webhooks go through the endpoint server
- added HMAC auth between Docusign and webhook endpoint
- added 'request data secret value' auth between Jotform and webhook endpoint
    - secret value is in request data but still hidden from the front end (sent as a POST from jotform)
- requests for redirecting to existing docusign forms go through the endpoint server (auth w/ HMAC)
- requests from fwwebb "My Accounts" page go through endpoint server (auth w/ HMAC)
- requests from ERP BP programs go through endpoint server (auth w/ HMAC)

*******************************
DOCUMENTATION / REFERENCES USED
*******************************
*** JWT OAuth ***
- Docusign requires a JWT (Json Web Token) for API authentication
https://developers.docusign.com/platform/auth/jwt/jwt-get-token/
	- there is a function in DocuSign's API to create a JWT 
    - it's also possibly to create a JWT with php, which is what we're doing

*** OAuth 2.0 ***
- we use Auth Code Grant to force the user to sign into the DocuSign account
    - implemented for Credit Department Approve/Deny stages only
    - "Shared Access" (see below) is required to allow Credit Dept.'s Docusign's account access
https://developers.docusign.com/platform/auth/choose/
https://stackoverflow.com/questions/50356193/docusign-granting-consent-redirect-uri-variable

*** Manage Shared Access ***
- Share an account's envelopes with other accounts
    - shared access envelopes will appear in the browser
- Required for OAuth
https://developers.docusign.com/docs/esign-rest-api/esign101/concepts/envelopes/shared-access/
https://support.docusign.com/s/document-item?language=en_US&bundleId=pik1583277475390&topicId=imk1656607611659.html&_LANG=enus

*** Embeded signing in app ***
- setting the param 'client_user_id' when creating docusign envelopes will make the recipient an 'embeded signing' recipient
- we use 'embeded signing' workflow with the exception of the Tax Exempt forms, which send to a different email group
https://developers.docusign.com/docs/esign-rest-api/how-to/request-signature-in-app-embedded/
https://developers.docusign.com/docs/esign-rest-api/how-to/request-signature-email-remote/
https://developers.docusign.com/docs/esign-rest-api/how-to/request-signature-template-remote/

*** Responsive signing (sending forms via email), also called Remote Signing ***
- less control on the workflow, emails, etc.
- is used only for our Tax Exempt forms
https://developers.docusign.com/docs/esign-rest-api/esign101/concepts/responsive/#:~:text=DocuSign%20Responsive%20Signing%20is%20a,is%20automatically%20converted%20into%20HTML.
https://developers.docusign.com/docs/esign-rest-api/how-to/request-signature-template-remote/

*** Pre-populate the fields in a Docusign form ***
- is used to pre-populate Docusign form with Jotform values
https://developers.docusign.com/docs/esign-rest-api/how-to/set-envelope-tab-values/
https://stackoverflow.com/questions/38002865/pre-populating-template-default-tabs-with-rest-api

*** User Id ***
- user_id is unique to DocuSign accounts. Set user_id to set the "envelope owner"
    - envelope goes into their account, appears in their browser, they get the email in remote signing, etc.
- envelopes can be shared and transfered
    - "Shared Access" in docusign dashboard (see 'Manage Shared Access' above)
https://support.docusign.com/s/articles/Controlling-Recipient-Email-Notifications?language=en_US&rsc_301

*** Pausing/Unpause the envelope workflow + updating envelope ***
- is used to make changes on the form before resending it for the next stage
    - for example, the individual PG forms (not the credit app ones) have a "Cust #" field we can use to update the envelope's name before sending credit the notification
https://developers.docusign.com/docs/esign-rest-api/reference/envelopes/envelopes/update/
https://developers.docusign.com/docs/esign-rest-api/how-to/pause-workflow/
https://developers.docusign.com/docs/esign-rest-api/how-to/unpause-workflow/
https://stackoverflow.com/questions/40742339/docusign-api-changing-the-filename-when-signing-from-a-template

*** Docusign Webhook Events ***
- `webhooks/docusign.php`
- edit events in Docusign -> Settings -> Webhook
    - 'env-completed', 'recipient-completed', 'finish later', etc.
- errors on this webhook will appear in Docusign 'Connect' (in settings)
- errors will auto retry w/ Docusign 'Connect', goto Docusign to manually retry the request
https://www.docusign.com/blog/developers/common-api-tasks-get-connect-failure-logs-and-attempt-to-retry-webhook-call
- the Webhook will 'retry' the same event if it gets a 400 status code or above

*** 'Finish Later' feature ***
- this is an optional webhook event (see above: 'Docusign Webhook Events')
https://stackoverflow.com/questions/36557928/docusign-when-using-an-embedded-signer-how-do-you-send-email-to-user-if-they-s
https://developers.docusign.com/docs/esign-soap-api/reference/administrative-group/embedded-callback-event-codes/

*** Session Timeout ***
- there isn't a webhook event for these ?!?!
- use the return url query param: Docusign sends the "event" query param as "session_timeout"
https://stackoverflow.com/questions/64347006/docusign-embedded-signing-how-can-i-increase-the-embedded-url-expiry-time

*** Move Envelopes to Folders ***
- folder_id's are specific to each docusign account/user
- defaults to "Sent" folder as the envelope owner is "sending" the form out
- subfolders exist such as "Completed", "Action Required", "Waiting for Others"
https://developers.docusign.com/docs/esign-rest-api/reference/folders/folders/moveenvelopes/
    - use api methods to move to inbox if required

*** JotForm CSS Injection ***
- useful but we're already using the source code and can customize css ourselves
https://www.jotform.com/help/117-how-to-inject-custom-css-codes/

*** JotForm sometimes CAPTCHA shows ***
- i think it's when you add too much custom code to the source code
- follow steps above (in "CHANGING THE FORMS USED") to avoid
https://www.jotform.com/answers/726779-using-full-source-code-of-the-form-shows-captcha-after-submitting
https://www.jotform.com/answers/490117-captcha-being-asked-on-source-code-embedded-jotform

*** jotform adding dynamic dropdown values ***
- for dynamic branch loading (dropdown appears only when selecting own branch)
https://www.jotform.com/answers/2265146-ability-to-populate-dropdown-options-from-an-external-or-internal-source
https://www.jotform.com/answers/2388547-populating-drop-down-list-from-database
https://www.jotform.com/help/442-how-to-use-the-spreadsheet-to-form-widget/

*** Find closest location based on zip lat/lng ***
- Jerry's code (`locationsModal.php` on fwwebb.com)
- see below for calc'ing distance between two points:
https://www.geodatasource.com/developers/php

*** New docusign accounts / new oauth permission *** 
- required setup for an account to be an "envelope owner"
- need to do this in the Docusign-dev sandbox account for new test accounts
https://www.docusign.com/blog/developers/oauth-jwt-granting-consent
- !! Redirect URI (`redirect_uri`) must match in docusign and the url below !!
    - set `redirect_uri` in Docusign Dashboard -> Settings
- go to the following url, sign into docusign account, then click 'allow', then be redirected to the redirect_uri
- confirm it works by hitting the api with their account id and email
- Example:
    - set `redirect_uri` in Docusign: https://testwww.fwwebb.com/docusign_auth_thank_you.html
    - go to this url and sign into Docusign:
        - https://account.docusign.com/oauth/auth?response_type=code&scope=signature%20impersonation&client_id=7df3a66d-8dd7-43c2-9d37-5b4ea1fc8c38&redirect_uri=https://testwww.fwwebb.com/docusign_auth_thank_you.html
    - confirm it worked with api

=============================
 Stockpile Express Additions
============================= 

*** Stockpile Express Customer Registration Additions / Changes ***
- Added classes for stockpile express (spx_com_helper, spx_docusign_helper, spx_email_helper, spx_api_endpoint, spx_docusign_webhook, spx_jotform_webhook, spx_cron_leads)
- Added the URLS for test and production stockpile express to the globals file to reference for spx classes
- Added the folder for spx_templates with the new tabs for dev and production, and .pdfs of the forms as well for stockpile express
- SPX_email_helper makes sure that all emails are now spx branded for stockpile flow, included stockpile logos added to server. 
- The flow and functionality remain the same as FWWEBB customer registration. 
- Changes to the docusign_webhook explained below 

*** Docusign URL to Publish (docusign_webhook.php) ***
- The URL to publish is found within docusign -> admin -> connect -> actions -> edit 
- There can only be one url to publish within the configuration 
- In order to include stockpile express with the same flow, we added code to the exisiting docusign_webhook file 
- We added a check that extracts the envelope id and checks if the branch = 125, if the branch does equal 125 it will use the "spx_docusign_webhook" file then exit once that is complete
- If the branch is anything other than 125, we will continue through the file as normal 

*** Stockpile Express Changes For ERP Helper ***
- New Save Customer file is created for Stockpile Express
- New functions for W4INTSUBCUSTREGSPX, Write Customer, Save Customer, and Delete Customer for SPX are created for the use of the new save customer file for stockpile express. 

*** Webhook Redirect Files ***
- Webhook_redirect and webhook_redirect2.php are both files created for stockpile testing 
- Webhook_redirect was testing the ability to include and use different files in if else statements within that file 
- Webhook_redirect2 was testing the new version of docusign_webhook before the changes were pushed to the docusign_webhook changes to include the spx check to use spx_docusign_webhook
- Webhook_redirect2 is now an exact working copy of now docusign_webhook (including spx check)
- Docusign_webhook.php is the URL to Publish within docusign for the customer registartion configuration (includes spx and fwwebb)
- Docusign_webhook.php includes spx check, the inclusion and use of spx_docusign_webhook.php within the file, and everything that it had for fwwebb before as well. 
- Webhook_redirect and webhook_redirect2 can be considered testing files for stockpile and are not actually in use anywhere. 

- NOTE ( - Jake: ) Webhook_redirect and webhook_redirect2.php have been moved to devScripts/ (excluded from the prod)
    - moved for migrating this codebase out of the DMZ (webhooks are no longer externally accessible)
    - see "7/30/2025 UPDATE" above


===============================
    Endpoints Documentation
===============================
*event --> dmz-endpoint --> here*
*All dmz-endpoints prefixed with customer-registration/v1/*

- Customer registration 
    - On submit, form data --> jotform-webhook  --> webhooks/jotform
    - On submit, redirect  --> redirect         --> api/redirect-registration

- Docusign
    - On submit, form data --> docusign-webhook  --> webhooks/docusign
    - On submit, redirect  --> redirect-docusign --> api/redirect-docusign
