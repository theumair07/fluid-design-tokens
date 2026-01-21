# Fluid Design Tokens

A powerful WordPress plugin for managing fluid design tokens using CSS custom properties with automatic viewport detection.

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0+-blue.svg?style=for-the-badge&logo=wordpress" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="License">
</p>

<p align="center">
  <img src="https://img.shields.io/github/stars/theumair07/fluid-design-tokens?style=social" alt="GitHub Stars">
  <img src="https://img.shields.io/github/forks/theumair07/fluid-design-tokens?style=social" alt="GitHub Forks">
  <img src="https://img.shields.io/github/watchers/theumair07/fluid-design-tokens?style=social" alt="GitHub Watchers">
</p>

<p align="center">
  <img src="https://img.shields.io/github/issues/theumair07/fluid-design-tokens" alt="Issues">
  <img src="https://img.shields.io/github/issues-pr/theumair07/fluid-design-tokens" alt="Pull Requests">
  <img src="https://img.shields.io/github/last-commit/theumair07/fluid-design-tokens" alt="Last Commit">
  <img src="https://img.shields.io/github/repo-size/theumair07/fluid-design-tokens" alt="Repo Size">
</p>

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🎯 **Fluid Design Tokens** | Create responsive tokens that automatically scale between mobile and desktop viewports |
| 🔌 **Elementor Integration** | Automatic viewport detection from Elementor settings |
| 📦 **Static Tokens** | Support for fixed-value tokens alongside fluid tokens |
| 🎨 **Clean Admin Interface** | Modern, intuitive admin panel for easy token management |
| 📋 **One-Click Copy** | Copy token syntax instantly to clipboard |
| 🔍 **Search Functionality** | Quickly find tokens by name |
| 📥 **Import/Export** | Export and import tokens as JSON files |
| ⚙️ **Flexible Root Font Size** | Choose between `100%` (1rem = 16px) or `62.5%` (1rem = 10px) |

---

## 🚀 Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/fluid-design-tokens/` directory
3. Activate the plugin through the **Plugins** menu in WordPress
4. Navigate to **Design Tokens** in the admin menu to start creating tokens

---

## 📖 Usage

### Creating Fluid Tokens

1. Go to **Design Tokens** in the WordPress admin menu
2. Enter a token name (e.g., `h1`, `section-padding`)
3. Set minimum size (rem) for mobile viewports
4. Set maximum size (rem) for desktop viewports
5. Click **Add Token**

### Creating Static Tokens

1. Scroll to the **Static Tokens** section
2. Enter a token name (e.g., `border-width`, `gap`)
3. Set the fixed value (rem)
4. Click **Add Static Token**

### Using Tokens in CSS

```css
/* Typography */
h1 {
    font-size: var(--h1);
}

/* Spacing */
.section {
    padding: var(--section-padding) 0;
}

/* Multiple Properties */
.card {
    padding: var(--card-padding);
    gap: var(--card-gap);
    font-size: var(--body-text);
}

/* Static Tokens (prefixed with --fs-) */
.element {
    border-width: var(--fs-border-width);
}
```

---

## ⚙️ Configuration

### Root Font Size

| Setting | Description |
|---------|-------------|
| `100%` | 1rem = 16px (browser default) |
| `62.5%` | 1rem = 10px (easier calculations) |

### Viewport Range

| Scenario | Viewport Range |
|----------|----------------|
| **With Elementor** | Uses your Elementor viewport settings |
| **Without Elementor** | Default: 320px - 1200px |

---

## 🔧 How It Works

The plugin uses the CSS `clamp()` function to create fluid values:

```css
--token-name: clamp(min-size, viewport-calculation, max-size);
```

This ensures your design tokens smoothly scale between the minimum and maximum values based on the current viewport width.

---

## 📦 Import/Export

| Action | Steps |
|--------|-------|
| **Export** | Click **Export** button → JSON file downloads |
| **Import** | Click **Import** button → Select JSON file → New tokens added |

> **Note:** Existing tokens with the same name are skipped during import.

---

## 🤝 Credits

### Original Developer

<a href="https://github.com/iamwaqasdotcom">
  <img src="https://img.shields.io/badge/Waqas_Ahmed-Original_Developer-blue?style=for-the-badge&logo=github" alt="Waqas Ahmed">
</a>

- 🌐 Website: [iamwaqas.com](https://iamwaqas.com)
- 💻 GitHub: [@iamwaqasdotcom](https://github.com/iamwaqasdotcom)

### Contributor

<a href="https://github.com/theumair07">
  <img src="https://img.shields.io/badge/Umair_Khan-Contributor-green?style=for-the-badge&logo=github" alt="Umair Khan">
</a>

- 🌐 Website: [umairyousafzai.com](https://umairyousafzai.com/)
- 💻 GitHub: [@theumair07](https://github.com/theumair07)

---

## 🌟 Show Your Support

Give a ⭐ if this project helped you!

<a href="https://github.com/theumair07/fluid-design-tokens/stargazers">
  <img src="https://img.shields.io/github/stars/theumair07/fluid-design-tokens?style=for-the-badge&logo=github" alt="Star this repo">
</a>

---

## 📄 License

MIT License

Copyright (c) 2025 Waqas Ahmed ✪

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/iamwaqasdotcom">Waqas Ahmed</a> & <a href="https://github.com/theumair07">Umair Khan</a>
</p>
