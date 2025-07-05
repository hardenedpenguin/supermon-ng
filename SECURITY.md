# Supermon-ng Security Documentation

## Overview

This document outlines the security measures implemented in Supermon-ng to protect against common web application vulnerabilities.

## Security Features Implemented

### 1. Session Security

- **Session Timeout**: 8-hour automatic timeout
- **Secure Cookies**: HTTP-only, secure flag when HTTPS detected, SameSite=Strict
- **Session Regeneration**: New session ID on login to prevent session fixation
- **Session Cleanup**: Proper session destruction on logout

### 2. Authentication & Authorization

- **Rate Limiting**: 5 login attempts per 15 minutes
- **Password Validation**: Minimum 8 characters with complexity requirements
- **User Permissions**: Role-based access control for different functions
- **Secure Logout**: Complete session and cookie cleanup

### 3. Input Validation & Sanitization

- **CSRF Protection**: All forms protected with CSRF tokens
- **Input Sanitization**: All user inputs validated and sanitized
- **Node Number Validation**: Strict validation for node parameters
- **File Path Validation**: Whitelist approach for file access

### 4. Command Injection Prevention

- **Safe Command Execution**: All system commands use `escapeshellcmd()` and `escapeshellarg()`
- **Command Path Validation**: Only allowed commands can be executed
- **Input Validation**: Strict validation for GPIO pins and system commands

### 5. XSS Prevention

- **Output Encoding**: All output uses `htmlspecialchars()` with proper encoding
- **Content Security Policy**: Strict CSP headers implemented
- **Input Sanitization**: All user inputs are sanitized before processing

### 6. Security Headers

- **X-Content-Type-Options**: nosniff
- **X-Frame-Options**: DENY
- **X-XSS-Protection**: 1; mode=block
- **Referrer-Policy**: strict-origin-when-cross-origin
- **Permissions-Policy**: Restricted permissions
- **Content-Security-Policy**: Comprehensive CSP implementation

### 7. File Access Security

- **Path Validation**: Only allowed log files can be accessed
- **File Extension Whitelist**: Only allowed extensions for uploads
- **Directory Traversal Prevention**: Real path validation

### 8. Logging & Monitoring

- **Security Event Logging**: All security events logged
- **Login Attempt Logging**: Failed login attempts tracked
- **Rate Limit Cleanup**: Automatic cleanup of old rate limit files

## Security Files

### Core Security Files

1. **session.inc**: Session management and security
2. **csrf.inc**: CSRF protection utilities
3. **rate_limit.inc**: Rate limiting implementation
4. **security.inc**: Centralized security configuration
5. **login.php**: Secure authentication
6. **logout.php**: Secure session termination

### Security Configuration

- **SECURITY_MAX_LOGIN_ATTEMPTS**: 5 attempts
- **SECURITY_LOGIN_TIMEOUT**: 900 seconds (15 minutes)
- **SECURITY_SESSION_TIMEOUT**: 28800 seconds (8 hours)
- **SECURITY_PASSWORD_MIN_LENGTH**: 8 characters

## Security Best Practices

### For Administrators

1. **Regular Updates**: Keep the system and dependencies updated
2. **Strong Passwords**: Enforce strong password policies
3. **HTTPS**: Always use HTTPS in production
4. **Access Control**: Regularly review user permissions
5. **Log Monitoring**: Monitor security logs for suspicious activity
6. **Backup Security**: Secure backup files and configurations

### For Developers

1. **Input Validation**: Always validate and sanitize user inputs
2. **Output Encoding**: Encode all output for HTML context
3. **CSRF Protection**: Include CSRF tokens in all forms
4. **Command Execution**: Use safe command execution functions
5. **Error Handling**: Don't expose sensitive information in errors
6. **Session Management**: Proper session handling and cleanup

## Security Checklist

### Pre-Deployment

- [ ] HTTPS configured
- [ ] Strong passwords set
- [ ] File permissions correct
- [ ] Security headers enabled
- [ ] Rate limiting active
- [ ] CSRF protection implemented
- [ ] Input validation working
- [ ] Error reporting disabled in production

### Regular Maintenance

- [ ] Security updates applied
- [ ] Logs reviewed
- [ ] User permissions audited
- [ ] Backup integrity verified
- [ ] Rate limit files cleaned
- [ ] Session timeout appropriate

## Vulnerability Response

### Reporting Security Issues

If you discover a security vulnerability in Supermon-ng:

1. **Do not** publicly disclose the issue
2. Contact the maintainers privately
3. Provide detailed information about the vulnerability
4. Allow time for assessment and fix

### Incident Response

1. **Immediate Actions**:
   - Isolate affected systems
   - Preserve evidence
   - Assess scope of compromise

2. **Investigation**:
   - Review logs for suspicious activity
   - Check for unauthorized access
   - Identify root cause

3. **Recovery**:
   - Apply security patches
   - Reset compromised credentials
   - Restore from clean backups if necessary

4. **Post-Incident**:
   - Document lessons learned
   - Update security measures
   - Monitor for recurrence

## Security Testing

### Recommended Tools

- **OWASP ZAP**: Web application security testing
- **Nikto**: Web server vulnerability scanner
- **Nmap**: Network security scanner
- **Burp Suite**: Web application security testing

### Testing Checklist

- [ ] SQL injection testing
- [ ] XSS vulnerability testing
- [ ] CSRF protection testing
- [ ] Authentication bypass testing
- [ ] File upload security testing
- [ ] Session management testing
- [ ] Input validation testing
- [ ] Error handling testing

## Compliance

### Data Protection

- **Personal Data**: Minimize collection and storage
- **Data Retention**: Implement appropriate retention policies
- **Data Encryption**: Encrypt sensitive data at rest and in transit
- **Access Control**: Implement least privilege access

### Audit Requirements

- **Access Logs**: Maintain comprehensive access logs
- **Change Logs**: Log all configuration changes
- **Security Events**: Log all security-related events
- **Retention**: Maintain logs for appropriate periods

## Contact

For security-related questions or issues:

- **Security Email**: [security@example.com]
- **Bug Reports**: Use the project's issue tracker
- **Emergency**: Contact maintainers directly

## Version History

- **v1.0.7**: Initial security implementation
- **v1.0.8**: Enhanced CSRF protection and rate limiting
- **v1.0.9**: Added comprehensive security headers and input validation

---

**Note**: This security documentation should be reviewed and updated regularly as new threats emerge and security measures evolve. 