#!/bin/bash
# Code block to automate zipping the OpenCart 3 and OpenCart 4 extensions cleanly
set -e

# Define directories
ROOT_DIR=$(pwd)
DIST_DIR="${ROOT_DIR}/dist"

# Recreate distribution directory
rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"

echo "=========================================================="
echo "📦 Packaging Filecheck OpenCart Extensions..."
echo "=========================================================="

# ── PACKAGE OPENCART 3 ────────────────────────────────────────────────────────
if [ -d "oc3" ]; then
    echo "⚡ Packaging OpenCart 3..."
    cd "${ROOT_DIR}/oc3"
    
    # Generate the zip directly with the contents inside oc3/ placed at root
    zip -r "${DIST_DIR}/filecheck-oc3.ocmod.zip" upload/ install.xml -x "*.DS_Store" > /dev/null
    
    echo "✅ Success: dist/filecheck-oc3.ocmod.zip created."
fi

# ── PACKAGE OPENCART 4 ────────────────────────────────────────────────────────
if [ -d "oc4" ]; then
    echo "⚡ Packaging OpenCart 4..."
    cd "${ROOT_DIR}/oc4"
    
    # Generate the zip directly with the contents inside oc4/ placed at root
    zip -r "${DIST_DIR}/filecheck-oc4.ocmod.zip" admin/ catalog/ system/ install.json install.xml -x "*.DS_Store" > /dev/null
    
    echo "✅ Success: dist/filecheck-oc4.ocmod.zip created."
fi

echo "=========================================================="
echo "🎉 Build complete! Check the 'dist/' folder for your release zips."
echo "=========================================================="
