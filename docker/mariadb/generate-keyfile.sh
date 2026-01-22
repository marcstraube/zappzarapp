#!/bin/bash
# ============================================================================
# MariaDB Encryption Keyfile Generator
# ============================================================================
# Generates encryption keyfile for MariaDB table-level encryption at rest
#
# GDPR Art. 32: Encryption of personal data at rest
#
# IMPORTANT: This is OPTIONAL! Only enable if you need table-level encryption.
# Most applications don't need this - TLS + OS-level disk encryption is sufficient.
#
# Usage:
#   bash docker/mariadb/generate-keyfile.sh
#
# This will create: docker/mariadb/keyfile.key (gitignored)
# ============================================================================

set -e

KEYFILE="docker/mariadb/keyfile.key"

# Check if keyfile already exists
if [[ -f "$KEYFILE" ]]; then
    echo "⚠️  Keyfile already exists: $KEYFILE"
    read -p "Overwrite? (y/N): " OVERWRITE
    if [[ "$OVERWRITE" != "y" ]] && [[ "$OVERWRITE" != "Y" ]]; then
        echo "Operation cancelled."
        exit 0
    fi
    rm "$KEYFILE"
fi

# Generate keyfile with 3 keys (for key rotation)
# Format: key-id;hex-encoded-key
echo "Generating encryption keyfile..."

# Key ID 1 (current key - 256-bit AES)
KEY1_ID=1
KEY1_HEX=$(openssl rand -hex 32)  # 32 bytes = 256 bits

# Key ID 2 (rotation key - for future use)
KEY2_ID=2
KEY2_HEX=$(openssl rand -hex 32)

# Key ID 3 (backup key - for emergencies)
KEY3_ID=3
KEY3_HEX=$(openssl rand -hex 32)

# Write keyfile
cat > "$KEYFILE" <<EOF
$KEY1_ID;$KEY1_HEX
$KEY2_ID;$KEY2_HEX
$KEY3_ID;$KEY3_HEX
EOF

# Set restrictive permissions (read-only for owner)
chmod 600 "$KEYFILE"

echo "✓ Keyfile generated: $KEYFILE"
echo ""
echo "⚠️  IMPORTANT SECURITY NOTES:"
echo "  1. Keep this keyfile secure - it's needed to decrypt your data!"
echo "  2. Backup this keyfile in a secure location (encrypted backup storage)"
echo "  3. Never commit this keyfile to version control (already in .gitignore)"
echo "  4. If you lose this keyfile, encrypted data CANNOT be recovered!"
echo ""
echo "Next steps:"
echo "  1. Enable encryption in docker/mariadb/my.cnf (uncomment settings)"
echo "  2. Mount keyfile in compose.yaml (see comments)"
echo "  3. Create tables with ENCRYPTED=YES option"
echo ""
echo "Example CREATE TABLE:"
echo "  CREATE TABLE sensitive_data ("
echo "    id INT AUTO_INCREMENT PRIMARY KEY,"
echo "    data TEXT"
echo "  ) ENGINE=InnoDB ENCRYPTED=YES;"
