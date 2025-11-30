# ✅ **CONTRIBUTING.md**

````md
# Contributing to ERAG Laravel CLI Installer

Thank you for considering contributing to this project!  
Your contribution helps improve the installer for the entire Laravel community.

This document will guide you through the contribution process.

---

## 🧱 Code of Conduct

By participating, you agree to follow our Code of Conduct.  
Be respectful, constructive, and collaborative.

---

# 🚀 How to Contribute

## 1. Fork the Repository
Click the **Fork** button to create your own copy.

## 2. Clone Your Fork
```bash
git clone https://github.com/YOUR_USERNAME/laravel-cli-installer.git
````

## 3. Create a New Branch

Always create a branch for your work:

```bash
git checkout -b feature/my-new-feature
```

Use meaningful branch names:

* `feature/*` for new features
* `fix/*` for bug fixes
* `docs/*` for documentation

---

# 🛠 Development Workflow

## Install Dependencies

```bash
composer install
npm install   # only if UI assets exist
```

## Run Code Formatter (Laravel Pint)

```bash
composer lint
```

## Test Commands

Make sure the CLI installer commands run properly:

```bash
php artisan erag:app-install
php artisan erag:app-setup
```

---

# 🧪 Testing (Important)

Before submitting PR:

* Test System Checks
* Test DB connection flow
* Test ENV generation
* Test Admin account creation
* Test error handling and retry behavior

---

# 📄 Submitting a Pull Request

1. Commit your changes:

```bash
git commit -m "feat: added xyz feature"
```

2. Push branch:

```bash
git push origin feature/my-new-feature
```

3. Open a Pull Request:

    * Describe changes clearly
    * Mention the issue it fixes (if any)
    * Attach screenshots if UI-related

---

# ✨ Coding Standards

This project uses:

* **PSR-4 autoloading**
* **Laravel Pint** for styling
* Meaningful variable names
* No unused imports
* No commented-out dead code

---

# ❓ Questions?

Feel free to open:

* **Discussions**
* **Issues**
* **PR drafts**

We appreciate all contributions — small or big!

Thanks again for helping make **ERAG Laravel CLI Installer better**! 🙌
