# Supermon-ng Release Process

This document outlines the complete release process for Supermon-ng, including version management, testing, and distribution.

## 📋 Pre-Release Checklist

### Code Quality
- [ ] All tests pass (`./scripts/run-tests.sh`)
- [ ] Code linting passes (`./scripts/lint-code.sh`)
- [ ] Security scan completed
- [ ] Documentation updated
- [ ] Version numbers updated in `includes/common.inc`

### Testing
- [ ] Manual testing on clean environment
- [ ] Installer script tested
- [ ] Configuration files validated
- [ ] Browser compatibility tested
- [ ] Mobile responsiveness verified

### Documentation
- [ ] README.md updated
- [ ] CHANGELOG.md updated
- [ ] Installation guide verified
- [ ] API documentation current
- [ ] Security documentation reviewed

## 🏷️ Version Management

### Version Format
Supermon-ng uses semantic versioning: `MAJOR.MINOR.PATCH`

- **MAJOR**: Breaking changes, major new features
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, minor improvements

### Version Locations
Update these files when releasing:

1. **`includes/common.inc`** (Primary source)
   ```php
   $TITLE_LOGGED = "Supermon-ng V3.0.0 AllStar Manager";
   $TITLE_NOT_LOGGED = "Supermon-ng V3.0.0 AllStar Monitor";
   $VERSION_DATE = "August 10, 2025";
   ```

2. **`manifest.json`** (PWA manifest)
   ```json
   {
     "name": "Supermon-ng V3.0.0",
     "version": "3.0.0"
   }
   ```

3. **`package.json`** (if using npm)
   ```json
   {
     "version": "3.0.0"
   }
   ```

## 🚀 Release Process

### 1. Prepare Release Branch
```bash
# Create release branch
git checkout -b release/v3.0.0

# Update version numbers
# Edit includes/common.inc, manifest.json, etc.

# Commit version changes
git add .
git commit -m "Bump version to 3.0.0"
```

### 2. Run Quality Checks
```bash
# Run tests
./scripts/run-tests.sh

# Run linting
./scripts/lint-code.sh

# Security scan
./scripts/security-scan.sh  # If available
```

### 3. Create Release Package
```bash
# Create release tarball
./scripts/create-release.sh
```

This generates:
- `supermon-ng-3.0.0.tar.xz` (compressed package)
- `supermon-ng-3.0.0.tar.xz.sha256` (SHA256 checksum)
- `supermon-ng-3.0.0.tar.xz.sha512` (SHA512 checksum)
- `supermon-ng-3.0.0.tar.xz.md5` (MD5 checksum)

### 4. Validate Release Package
```bash
# Extract and test
cd /tmp
tar -xJf supermon-ng-3.0.0.tar.xz
cd supermon-ng-3.0.0

# Verify structure
ls -la
cat RELEASE_NOTES.md
cat INSTALL.md
```

### 5. Create GitHub Release
1. Go to GitHub repository
2. Click "Releases" → "Create a new release"
3. Tag: `v3.0.0`
4. Title: `Supermon-ng v3.0.0`
5. Upload release files:
   - `supermon-ng-3.0.0.tar.xz`
   - `supermon-ng-3.0.0.tar.xz.sha256`
   - `supermon-ng-3.0.0.tar.xz.sha512`
6. Copy content from `RELEASE_NOTES.md`

### 6. Update Installer Script
```bash
# Update installer script URL in README.md
# Ensure installer points to correct release
```

### 7. Merge to Main
```bash
# Merge release branch
git checkout main
git merge release/v3.0.0

# Push to repository
git push origin main
git push origin v3.0.0
```

## 📦 Release Package Contents

### Required Files
- [ ] All PHP application files
- [ ] CSS and JavaScript assets
- [ ] Configuration examples
- [ ] Documentation (README, INSTALL, RELEASE_NOTES)
- [ ] Installer script
- [ ] Security documentation
- [ ] PWA manifest and offline page

### Required Directories
- [ ] `includes/` - Core PHP libraries
- [ ] `user_files/` - Configuration examples
- [ ] `css/` - Stylesheets
- [ ] `js/` - JavaScript files
- [ ] `docs/` - Documentation
- [ ] `scripts/` - Utility scripts
- [ ] `tests/` - Test suite
- [ ] `templates/` - Development templates

### Excluded Files
- [ ] `.git/` directory
- [ ] Development files (`.vscode/`, `.idea/`)
- [ ] Temporary files (`*.tmp`, `*.log`)
- [ ] Build artifacts
- [ ] Node modules (if any)

## 🔍 Release Validation

### Automated Checks
The release script performs these validations:

1. **File Presence**: Checks for all required files
2. **Directory Structure**: Validates directory layout
3. **Version Extraction**: Confirms version can be read
4. **Checksum Generation**: Creates multiple checksums
5. **Package Integrity**: Validates tarball creation

### Manual Verification
After release creation, manually verify:

1. **Installation**: Test on clean system
2. **Functionality**: Verify all features work
3. **Documentation**: Check all links and instructions
4. **Security**: Review for sensitive data exposure

## 📈 Post-Release Tasks

### 1. Update Documentation
- [ ] Update main README with new version
- [ ] Update installation instructions
- [ ] Update changelog
- [ ] Update API documentation

### 2. Announce Release
- [ ] Post to community forums
- [ ] Update social media
- [ ] Send email notifications (if applicable)
- [ ] Update project website

### 3. Monitor Feedback
- [ ] Watch GitHub issues
- [ ] Monitor community discussions
- [ ] Track installation success rates
- [ ] Address any critical issues

## 🛠️ Troubleshooting

### Common Issues

**Version Extraction Fails**
```bash
# Check common.inc format
grep -n "VERSION_DATE\|TITLE_LOGGED" includes/common.inc
```

**Missing Required Files**
```bash
# Check what's missing
./scripts/create-release.sh 2>&1 | grep "missing"
```

**Checksum Mismatch**
```bash
# Verify checksums
sha256sum -c supermon-ng-3.0.0.tar.xz.sha256
```

### Emergency Procedures

**Hotfix Release**
```bash
# Create patch release
git checkout -b hotfix/v3.0.1
# Make fixes
./scripts/create-release.sh
```

**Rollback Release**
```bash
# Revert to previous version
git checkout v2.0.3
./scripts/create-release.sh
```

## 📚 Additional Resources

- [Developer Guide](DEVELOPER_GUIDE.md)
- [Contributing Guidelines](CONTRIBUTING.md)
- [Security Documentation](../SECURITY.md)
- [Installation Guide](../INSTALL.md)

## 🔗 Release Script Reference

The release script (`scripts/create-release.sh`) provides:

- **Version Extraction**: Reads from `includes/common.inc`
- **Package Creation**: Generates `.tar.xz` with proper structure
- **Documentation Generation**: Creates INSTALL.md and RELEASE_NOTES.md
- **Validation**: Checks package integrity
- **Checksums**: Creates SHA256, SHA512, and MD5 checksums

For script options and customization, see the script header comments.
