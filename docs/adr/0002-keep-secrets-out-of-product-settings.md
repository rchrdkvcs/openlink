# Keep secrets out of product settings

Openlink stores product-facing configuration in the instance admin panel or workspace settings, but keeps secrets and boot-time infrastructure values outside those panels. This lets the application be configured through the UI where it is safe while preserving predictable Laravel deployment and avoiding accidental exposure or mutation of credentials.
