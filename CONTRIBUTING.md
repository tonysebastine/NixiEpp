# Contributing to NixiEpp

Thank you for your interest in contributing to NixiEpp! This document provides guidelines and instructions for contributing.

---

## 🎯 Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on what's best for the community

---

## 🚀 Getting Started

### 1. Fork the Repository

Click the "Fork" button on GitHub to create your own copy.

### 2. Clone Your Fork

```bash
git clone https://github.com/YOUR_USERNAME/NixiEpp.git
cd NixiEpp
```

### 3. Create a Branch

```bash
git checkout -b feature/your-feature-name
```

---

## 📝 Development Guidelines

### PHP Standards

- **PHP Version**: 8.0+
- **Type Declarations**: Required for all parameters and return types
- **Strict Types**: Use `declare(strict_types=1);`
- **PSR-12**: Follow PSR-12 coding style
- **DocBlocks**: Document all public methods

### Example

```php
<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Registrar\NixiEpp;

/**
 * Example service class
 */
class ExampleService
{
    /**
     * Example method
     *
     * @param string $domain Domain name
     * @return bool Success status
     */
    public function exampleMethod(string $domain): bool
    {
        // Implementation
        return true;
    }
}
```

---

## 🧪 Testing

### Before Submitting

1. **Syntax Check**:
   ```bash
   php -l Service.php
   php -l EppClient.php
   # ... all PHP files
   ```

2. **Test Your Changes**:
   - Test in a development FOSSBilling installation
   - Verify no breaking changes
   - Test edge cases

3. **Update Documentation**:
   - Update relevant MD files
   - Add comments for new features
   - Update API_REFERENCE.md if needed

---

## 📤 Submitting Changes

### 1. Commit Your Changes

```bash
git add .
git commit -m "feat: add new feature description"
```

**Commit Message Format**:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes
- `refactor:` - Code refactoring
- `test:` - Test additions/changes
- `chore:` - Maintenance tasks

### 2. Push to Your Fork

```bash
git push origin feature/your-feature-name
```

### 3. Open a Pull Request

1. Go to the original repository
2. Click "New Pull Request"
3. Select your branch
4. Describe your changes
5. Submit

---

## 📋 Pull Request Guidelines

### Title

Clear and concise:
- ✅ `feat: add DNSSEC support`
- ✅ `fix: resolve connection timeout issue`
- ❌ `updated some stuff`

### Description

Include:
- **What** changed
- **Why** it changed
- **How** to test it
- **Related issues** (if any)

### Checklist

- [ ] Code follows PSR-12 standards
- [ ] Type declarations added
- [ ] DocBlocks updated
- [ ] Tests pass
- [ ] Documentation updated
- [ ] No breaking changes (or clearly documented)

---

## 🐛 Reporting Bugs

### Before Reporting

1. Check existing issues
2. Test with latest version
3. Gather information

### Bug Report Template

```markdown
**Describe the bug**
Clear description of the bug.

**To Reproduce**
Steps to reproduce:
1. Configure '...'
2. Run '...'
3. See error

**Expected behavior**
What should happen.

**Logs**
Relevant log output.

**Environment**
- PHP version: 8.x
- FOSSBilling version: x.x.x
- OS: Linux/Windows

**Additional context**
Any other details.
```

---

## 💡 Feature Requests

### Before Requesting

1. Check existing issues
2. Ensure it aligns with project goals
3. Consider implementation complexity

### Feature Request Template

```markdown
**Is your feature related to a problem?**
Description of the problem.

**Describe the solution**
Clear description of what you want.

**Describe alternatives**
Alternative solutions considered.

**Additional context**
Mockups, examples, references.
```

---

## 📚 Documentation

### When to Update

- New features added
- API changes
- Configuration changes
- Bug fixes that change behavior

### Documentation Standards

- Use Markdown format
- Include code examples
- Keep it concise
- Update relevant files:
  - README.md (overview)
  - INSTALL.md (installation)
  - API_REFERENCE.md (API changes)
  - LIFECYCLE.md (lifecycle changes)

---

## 🔍 Code Review Process

1. **Automated Checks**:
   - PHP syntax validation
   - Code style check

2. **Maintainer Review**:
   - Code quality
   - Architecture alignment
   - Security implications

3. **Testing**:
   - Functional testing
   - Integration testing

4. **Merge**:
   - Squash and merge
   - Delete branch

---

## 🎓 Resources

- [EPP RFC 5730-5734](https://tools.ietf.org/html/rfc5730)
- [FOSSBilling Documentation](https://fossbilling.org/docs)
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)
- [PHP Manual](https://www.php.net/manual/)

---

## 🙏 Thank You!

Your contributions make NixiEpp better for everyone. We appreciate your time and effort!

---

**Questions?** Open an issue or contact the maintainers.
