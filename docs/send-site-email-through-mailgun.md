---
title: Send your site's email through Mailgun
tags: email, mailgun, mail provider, smtp, sending, deliverability
---

**Mailgun** sends your site's email, such as order confirmations and sign-in codes, through Mailgun's service instead of SMTP. It is useful where your hosting blocks outbound SMTP.

## Set it up

1. In your Mailgun account, get the details below.
2. Open **Mailgun Settings** and enter your **Sending Domain**, **Region** and **API Key**.
3. Press **Save Settings**.
4. Open the **Email** settings page and choose **Mailgun** as the **Provider**. The **From Address** and the **Send test email to** button are on that page.

Use **Run diagnostics** on the Email settings page if mail does not arrive.
