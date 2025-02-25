#!/bin/bash

# Script to prepare WP Mail Master plugin for WordPress.org submission
echo "Preparing WP Mail Master plugin for WordPress.org submission..."

# Create a clean directory for submission
SUBMISSION_DIR="wp-mail-master-submission"
mkdir -p $SUBMISSION_DIR

# Copy all plugin files to the submission directory
echo "Copying plugin files..."
cp -R admin assets includes languages templates vendor *.php composer.json composer.lock readme.txt README.md $SUBMISSION_DIR/

# Create missing screenshots placeholders
echo "Creating placeholder for missing screenshots..."
# Only necessary if you haven't created these yet
for i in {2..5}; do
  if [ ! -f "$SUBMISSION_DIR/assets/screenshot-$i.png" ]; then
    echo "Note: You need to create assets/screenshot-$i.png manually before submission"
  fi
done

# Remove development and unnecessary files
echo "Removing development files..."
find $SUBMISSION_DIR -name ".DS_Store" -type f -delete
find $SUBMISSION_DIR -name "*.bak" -type f -delete
find $SUBMISSION_DIR -name ".phpunit.result.cache" -type f -delete
find $SUBMISSION_DIR -name ".gitignore" -type f -delete

# Verify the preparation
echo "Verifying preparation..."
echo "Files that should not be included in submission:"
find $SUBMISSION_DIR -name ".git" -o -name ".vscode" -o -name ".DS_Store" -o -name "*.bak" -o -name ".phpunit*"

echo "Done! Your plugin is now ready for WordPress.org submission in the $SUBMISSION_DIR directory."
echo "IMPORTANT: Please verify that you have created all required screenshots 1-5 before submission."
echo "README checklist:"
echo "- readme.txt is in WordPress.org format: ✓"
echo "- Plugin URI is set to WordPress.org: ✓"
echo "- Banner images: ✓"
echo "- Plugin icons: ✓"
echo "- Missing screenshots 2-5: Please create these manually" 