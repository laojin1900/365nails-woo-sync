# 365nails Sync (WooCommerce plugin)

Connects a merchant's WooCommerce store to their **365nails dropshipping membership**: orders flow into the 365nails portal, and fulfillment flows back — tracking number, carrier, and tracking link visible **inside the merchant's own order admin and in their customers' shipping emails**.

Companion to the dropshipper portal (`dropshipper-portal`). Design and the source-verified WooCommerce facts it is built on: `docs/channel-expansion-briefs/p1d-wp-plugin-brief.md` in that repo.

## What it is / is not

| It is | It is NOT |
|---|---|
| The merchant-visible half of the Woo channel (order panel, shipping email) | A webhook receiver — WooCommerce delivers straight to the portal's signed endpoint |
| HPOS-compatible from day one | Something that writes delivery-time promises into the store (that's the merchant's storefront, not ours) |
| Distributed as a **zip from GitHub Releases** | A WordPress.org directory listing (decision T22, 2026-09-18: self-hosted zip until scale justifies otherwise) |

## Install

1. Download `365nails-sync.zip` from [Releases](../../releases).
2. WordPress admin → 插件 → 安装插件 → 上传插件 → choose the zip → activate.
3. Requires: WooCommerce installed and active.

## Upgrade

Download the newer zip from Releases and upload it over the old one (WordPress asks to replace). **The plugin is uninstalled-safe**: deactivation and replacement never delete your webhooks, order meta, or data.

## Development

- `365nails-sync.php` — plugin header + HPOS compatibility declaration.
- `includes/` — one file per concern; `class-365nails-plugin.php` is the table of contents.
- CI: `php -l` syntax check on every push/PR.
- Release: push a tag `v*` and the workflow builds `365nails-sync.zip` and attaches it to the GitHub release.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
