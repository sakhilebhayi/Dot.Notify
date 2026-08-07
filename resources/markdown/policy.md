_Last updated: 7 August 2026_

This Privacy Policy explains how **BluePin Inc** ("BluePin", "we", "us", "our"), the company responsible for Dot.Notify, collects, uses, stores, and shares personal information when you use Dot.Notify and the wider Dot Ecosystem it connects to. It is written to align with South Africa's **Protection of Personal Information Act 4 of 2013 ("POPIA")**.

Dot.Notify is the Dot Ecosystem's shared notification last mile — it sends, tracks, and routes notifications on behalf of other platforms. That shapes this Policy differently from most of our other platforms.

## 1. Who we are

BluePin Inc is the responsible party for the personal information described in this Policy. Our Information Officer can be reached at privacy@infodot.co.za for any question, request, or concern about your personal information.

## 2. Two kinds of personal information Dot.Notify handles

- **Your account information**, if you sign in to Dot.Notify to configure channels, templates, and rules for your team — here, BluePin is the **responsible party**, and this Policy governs how we handle it.
- **Recipient and notification data**, when another Dot Ecosystem platform (or a team's own configured trigger) asks Dot.Notify to deliver a notification. Here, the platform or team that triggered the notification is the responsible party for the recipient's contact details and message content, and BluePin acts as an **operator**, sending and tracking that notification on their instructions. If you received a notification and have a question about why, contact the platform or team that sent it, not Dot.Notify directly.

## 3. What we collect (account holders)

**Account information** — your name, email address, and password (stored as a salted hash, never in plain text), via your Dot Ecosystem account.

**Team information** — the team you belong to, and the channels, templates, rules, and webhooks your team configures.

**Technical information** — IP address, browser and device information, and session activity, collected automatically for security and to keep you signed in.

## 4. What we process on a team's behalf (recipients)

When a team or another platform triggers a notification, we process:

- **Recipient details** — whatever contact information the channel requires (an email address, phone number, push token, Slack workspace/channel, or webhook URL), supplied by the triggering team, not collected by us directly from the recipient.
- **Message content** — the subject and body of the notification, built from a team's own template.
- **Delivery and engagement status** — whether a notification was queued, sent, delivered, opened, or clicked, and whether it failed or bounced. Opens and clicks are tracked using a tracking pixel or link redirect, standard practice for delivery/engagement measurement across the ecosystem.
- **Notification preferences** — if you're the recipient and also have a Dot Ecosystem account, your opt-in/opt-out choice per notification type and channel is recorded and respected.

We process this data only as instructed by the team or platform that triggered the notification — we don't use it for our own marketing, and we don't sell it.

## 5. Inbound webhooks from third parties

Some teams configure Dot.Notify to receive events directly from third-party systems (for example, a payment processor or code-hosting platform), which we verify using a signed request scheme before routing them into a notification. Any personal information in those inbound payloads is processed on the receiving team's instructions, on the same operator basis described in §2.

## 6. AI-assisted template drafting

Dot.Notify offers an optional AI drafting assistant for notification templates. When used, only the template's stated purpose and channel type are sent to Anthropic's API to generate a suggested subject/body structure — actual recipient data is never included in that request. When AI drafting isn't configured or available, a deterministic non-AI template is generated instead, entirely on our own infrastructure.

## 7. Why we process your information

We process personal information to:

- create and maintain your account, and authenticate you when you sign in;
- let you sign in once and move between connected Dot Ecosystem platforms without re-entering your credentials;
- let your team configure channels, templates, rules, and webhooks, and route and send notifications through them; and
- keep Dot.Notify secure and prevent abuse.

## 8. Ecosystem single sign-on

When you use another Dot Ecosystem platform to sign in to Dot.Notify (or vice versa), a short-lived, single-use authentication token confirms who you are without exposing your password to the connected platform.

## 9. Who can see notification data

Channels, templates, rules, and delivery logs are scoped to the team that owns them. We don't share one team's notification data with another team, or with any platform other than the one that triggered the send.

## 10. How long we keep your information

We keep account and team-configuration data for as long as your account or team is active. Delivery logs are kept to provide the audit trail teams rely on; if you delete your account or a team removes a configuration, the associated data is removed, except where we're required by law to retain certain records for longer.

## 11. Security

We apply reasonable technical and organisational measures to protect personal information, including encrypted password storage, team-scoped access control, HMAC-signed inbound webhook verification, and single-use SSO tokens for ecosystem sign-in. No system is perfectly secure, and we can't guarantee absolute security.

## 12. Your rights under POPIA

Subject to applicable law, you have the right to:

- request access to the personal information we hold about you;
- request correction of inaccurate or incomplete information;
- request deletion of your personal information;
- object to our processing of your personal information in certain circumstances; and
- lodge a complaint with the Information Regulator of South Africa.

If your request concerns a notification you received rather than a Dot.Notify account, we may need to direct you to the platform or team that sent it, since they are the responsible party for that data. To exercise any of these rights regarding your Dot.Notify account, contact privacy@infodot.co.za.

## 13. Cookies

Dot.Notify uses a session cookie to keep you signed in. See our Cookie Policy for details.

## 14. Changes to this Policy

We may update this Privacy Policy from time to time. If we make material changes, we'll update the "Last updated" date above and, where appropriate, notify you directly.

## 15. Contact us

If you have questions about this Privacy Policy or how we handle your personal information, contact us at privacy@infodot.co.za.
