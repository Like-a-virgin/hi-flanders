#!/bin/bash

# ---------------------
# CONFIGURATION
# ---------------------

REMOTE_USER="liveavirginbe"
REMOTE_HOST="ssh.liveavirgin.be"
REMOTE_DB_HOST="ID181052_sthiflandres.db.webhosting.be"
REMOTE_DB_NAME="ID181052_sthiflandres"
REMOTE_DB_USER="ID181052_sthiflandres"
REMOTE_DB_PASS="L1ke@V1rg1n"
REMOTE_BACKUP_NAME="db-backup.sql"
LOCAL_BACKUP_PATH="./$REMOTE_BACKUP_NAME"

# ---------------------
# 0. Ensure DDEV is running
# ---------------------
if ! ddev describe > /dev/null 2>&1; then
  echo "🚀 Starting DDEV..."
  ddev start 
  echo "✅ DDEV started."
fi

# ---------------------
# 1. Dump the remote DB
# ---------------------
echo "🔐 Connecting to $REMOTE_HOST to create DB dump..."
ssh $REMOTE_USER@$REMOTE_HOST "mysqldump -h $REMOTE_DB_HOST -u $REMOTE_DB_USER --password=\"$REMOTE_DB_PASS\" $REMOTE_DB_NAME > $REMOTE_BACKUP_NAME"

if [ $? -ne 0 ]; then
  echo "❌ Remote dump failed. Check credentials or SSH access."
  exit 1
fi

# ---------------------
# 2. Download the dump
# ---------------------
echo "📥 Downloading DB dump to your machine..."
scp $REMOTE_USER@$REMOTE_HOST:$REMOTE_BACKUP_NAME $LOCAL_BACKUP_PATH

if [ $? -ne 0 ]; then
  echo "❌ Download failed. Check SCP access or paths."
  exit 1
fi
# ---------------------
# 3. Make backup of local DB
# ---------------------
echo "💾 Backing up your current local database..."
ddev export-db --file=local-backup.sql
# ---------------------
# 4. Import into DDEV
# ---------------------
echo "🛠️ Importing into local DDEV database..."
ddev import-db --src=$LOCAL_BACKUP_PATH

if [ $? -eq 0 ]; then
  echo "✅ Done! Live DB imported into your DDEV environment."
else
  echo "❌ DDEV import failed. Check if DDEV is running."
fi