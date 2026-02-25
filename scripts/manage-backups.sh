#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base directory for backups
BACKUP_DIR="storage/app/backups"

# Function to show backup status
show_status() {
    echo -e "${YELLOW}=== Backup Status ===${NC}"
    
    # Check if backup directory exists
    if [ ! -d "$BACKUP_DIR" ]; then
        echo -e "${RED}Backup directory does not exist!${NC}"
        return 1
    }

    # Count total backups
    BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/*.sql 2>/dev/null | wc -l)
    echo -e "Total backups: ${GREEN}$BACKUP_COUNT${NC}"

    # Show latest backup
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/*.sql 2>/dev/null | head -n 1)
    if [ -n "$LATEST_BACKUP" ]; then
        LATEST_BACKUP_TIME=$(stat -f "%Sm" "$LATEST_BACKUP")
        echo -e "Latest backup: ${GREEN}$(basename "$LATEST_BACKUP")${NC}"
        echo -e "Created at: ${GREEN}$LATEST_BACKUP_TIME${NC}"
        
        # Check backup file size
        BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
        echo -e "Backup size: ${GREEN}$BACKUP_SIZE${NC}"
    else
        echo -e "${RED}No backups found!${NC}"
    fi

    # Check disk space
    echo -e "\n${YELLOW}=== Disk Space ===${NC}"
    df -h "$BACKUP_DIR"
}

# Function to verify backup integrity
verify_backup() {
    if [ -z "$1" ]; then
        echo -e "${RED}Please provide a backup file to verify${NC}"
        return 1
    }

    BACKUP_FILE="$BACKUP_DIR/$1"
    
    if [ ! -f "$BACKUP_FILE" ]; then
        echo -e "${RED}Backup file not found: $1${NC}"
        return 1
    }

    echo -e "${YELLOW}Verifying backup: $1${NC}"
    
    # Check if file is readable
    if [ ! -r "$BACKUP_FILE" ]; then
        echo -e "${RED}Backup file is not readable!${NC}"
        return 1
    }

    # Check if file is not empty
    if [ ! -s "$BACKUP_FILE" ]; then
        echo -e "${RED}Backup file is empty!${NC}"
        return 1
    }

    # Try to read the first few lines of the SQL file
    if head -n 1 "$BACKUP_FILE" | grep -q "MySQL dump"; then
        echo -e "${GREEN}Backup appears to be valid${NC}"
        return 0
    else
        echo -e "${RED}Backup file may be corrupted${NC}"
        return 1
    fi
}

# Function to list all backups
list_backups() {
    echo -e "${YELLOW}=== Available Backups ===${NC}"
    if [ -d "$BACKUP_DIR" ]; then
        ls -lh "$BACKUP_DIR"/*.sql 2>/dev/null
    else
        echo -e "${RED}Backup directory does not exist!${NC}"
    fi
}

# Main script
case "$1" in
    "status")
        show_status
        ;;
    "verify")
        verify_backup "$2"
        ;;
    "list")
        list_backups
        ;;
    *)
        echo -e "${YELLOW}Usage:${NC}"
        echo "  $0 status  - Show backup status"
        echo "  $0 verify [filename] - Verify a backup file"
        echo "  $0 list   - List all backups"
        ;;
esac 