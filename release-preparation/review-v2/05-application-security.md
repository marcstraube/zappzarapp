# Review 05: Application Security

**Role**: Application Security Expert (OWASP Focus)

**Weight**: 8% of final score

**Report Location**: `reports/review-05-application-security.md`

---

## Verification Commands

```bash
# Check security headers in nginx
grep -r "X-Frame-Options\|X-Content-Type\|X-XSS\|CSP\|HSTS" docker/nginx/

# Check CORS configuration
grep -r "CORS\|Access-Control" src/ docker/nginx/

# Check for SQL injection patterns
grep -rn "query.*\$\|execute.*\$" src/php/ --include="*.php"
```

---

## Analysis Checklist

### A. OWASP Top 10 Verification

1. **Injection**
   - [ ] Prepared statements everywhere
   - [ ] No string concatenation in queries
   - [ ] Input validation present
   - [ ] ORM/query builder used correctly

2. **Broken Authentication**
   - [ ] Session management secure
   - [ ] Password hashing (bcrypt/argon2)
   - [ ] No credential exposure in logs
   - [ ] Rate limiting implemented

3. **Sensitive Data Exposure**
   - [ ] TLS everywhere
   - [ ] Encryption at rest where applicable
   - [ ] No sensitive data in URLs
   - [ ] Proper error handling (no stack traces)

4. **XXE**
   - [ ] XML parsing disabled or secured
   - [ ] DTD processing disabled

5. **Broken Access Control**
   - [ ] Authorization checks present
   - [ ] No IDOR vulnerabilities in examples

6. **Security Misconfiguration**
   - [ ] Debug mode off by default
   - [ ] Default credentials changed
   - [ ] Error pages don't leak info
   - [ ] Directory listing disabled

7. **XSS**
   - [ ] Output encoding/escaping
   - [ ] CSP headers configured
   - [ ] Template auto-escaping enabled

8. **Insecure Deserialization**
   - [ ] No unserialize() on user input
   - [ ] JSON preferred over serialize

9. **Using Components with Known Vulnerabilities**
   - [ ] Dependencies up to date
   - [ ] Security advisories checked
   - [ ] Audit commands in CI

10. **Insufficient Logging**
    - [ ] Security events logged
    - [ ] No sensitive data in logs
    - [ ] Log injection prevented

### B. Security Headers (Nginx)

- [ ] X-Frame-Options: SAMEORIGIN
- [ ] X-Content-Type-Options: nosniff
- [ ] X-XSS-Protection configured
- [ ] Referrer-Policy set
- [ ] Content-Security-Policy defined
- [ ] Strict-Transport-Security (production)
- [ ] Permissions-Policy considered

### C. CORS Configuration

- [ ] CORS_ORIGINS not set to '*' by default
- [ ] Allowed origins explicitly listed
- [ ] Credentials handling correct
- [ ] Preflight caching appropriate

### D. API Security

- [ ] Rate limiting present
- [ ] Input validation on all endpoints
- [ ] Authentication required where appropriate
- [ ] No sensitive data in GET parameters

---

## Output Format

See `00-overview.md` for standard report format.
