#!/bin/bash

# GitHub Auto-Deploy Plugin Lint Check Script
# Run this script to validate code quality

echo "🔍 GitHub Auto-Deploy Plugin - Code Quality Check"
echo "=================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0

# PHP Syntax Check
echo "📝 Checking PHP syntax..."
for file in $(find . -name "*.php"); do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo -e "${RED}✗ Syntax error in $file${NC}"
        ERRORS=$((ERRORS + 1))
    fi
done

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All PHP files have valid syntax${NC}"
fi

# Security Checks
echo ""
echo "🔒 Running security checks..."

# Check for debug statements
if grep -r "var_dump\|print_r\|die(" --include="*.php" . > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠ Warning: Debug statements found${NC}"
    grep -rn "var_dump\|print_r\|die(" --include="*.php" .
fi

# Check for eval
if grep -r "eval(" --include="*.php" . > /dev/null 2>&1; then
    echo -e "${RED}✗ eval() usage found (security risk)${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✓ No eval() usage${NC}"
fi

# Check for deprecated MySQL functions
if grep -r "mysqli_\|mysql_" --include="*.php" . > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠ Warning: Direct MySQL functions found (use wpdb)${NC}"
fi

# Check for file_get_contents with HTTP
if grep -r "file_get_contents.*http" --include="*.php" . > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠ Warning: file_get_contents with HTTP (use wp_remote_request)${NC}"
fi

# WordPress Standards
echo ""
echo "📋 Checking WordPress standards..."

# Check for ABSPATH
PHP_FILES=$(find . -name "*.php" | wc -l)
ABSPATH_COUNT=$(grep -l "ABSPATH" $(find . -name "*.php") | wc -l)

if [ $ABSPATH_COUNT -eq $PHP_FILES ]; then
    echo -e "${GREEN}✓ All PHP files check for ABSPATH${NC}"
else
    echo -e "${YELLOW}⚠ Warning: Not all files check for ABSPATH${NC}"
fi

# Check for proper escaping
if grep -r "echo.*\$_" --include="*.php" . > /dev/null 2>&1; then
    echo -e "${RED}✗ Unescaped output found${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✓ No unescaped user input in output${NC}"
fi

# JavaScript Checks
echo ""
echo "🟨 Checking JavaScript..."

if [ -f "admin/js/admin-scripts.js" ]; then
    if grep -n "console\.log\|debugger" admin/js/admin-scripts.js > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠ Warning: console.log/debugger found${NC}"
    else
        echo -e "${GREEN}✓ No debug statements in JavaScript${NC}"
    fi

    if grep -n "var " admin/js/admin-scripts.js > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠ Warning: 'var' declarations found (use const/let)${NC}"
    else
        echo -e "${GREEN}✓ Using const/let (no var)${NC}"
    fi
fi

# CSS Checks
echo ""
echo "🎨 Checking CSS..."

if [ -f "admin/css/admin-styles.css" ]; then
    IMPORTANT_COUNT=$(grep -c "!important" admin/css/admin-styles.css)
    if [ $IMPORTANT_COUNT -gt 10 ]; then
        echo -e "${YELLOW}⚠ Warning: High !important usage ($IMPORTANT_COUNT)${NC}"
    else
        echo -e "${GREEN}✓ Acceptable !important usage ($IMPORTANT_COUNT)${NC}"
    fi
fi

# Summary
echo ""
echo "=================================================="
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All checks passed! Code is ready for deployment.${NC}"
    exit 0
else
    echo -e "${RED}❌ Found $ERRORS error(s). Please fix before deployment.${NC}"
    exit 1
fi
