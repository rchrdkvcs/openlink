# Manage QR Codes as Workspace objects

QR Codes are created and managed only from the dedicated QR Codes area. Every QR Code belongs directly to one Workspace and has exactly one target: either a linked Short Link or a native direct payload. A Short Link may be linked from multiple QR Codes, but it is no longer the management surface or owner of those QR Codes.

Linked QR Codes encode a stable Openlink URL and retain scan attribution while inheriting the Short Link lifecycle, protection, and Smart Routing. Direct-payload QR Codes encode native content so scanners can perform actions such as joining Wi-Fi or importing a vCard; an exported native image cannot be updated remotely, so changing to or from a direct payload requires re-exporting and reprinting it.
