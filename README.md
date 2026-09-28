# MC Functionality

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress%20Plugin-1.2.0-blue.svg)](https://github.com/Roots-and-Fruit/mc-functionality)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-green.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Maintenance](https://img.shields.io/badge/Maintained%3F-yes-green.svg)](https://github.com/Roots-and-Fruit/mc-functionality/graphs/commit-activity)

A **file-based code snippet system** for WordPress that loads and executes PHP files with better performance and security than database-stored snippets.

## 🚀 Why MC Functionality?

### Performance Benefits
- **File-based storage** - No database queries for snippet loading
- **Early loading** - Unconditional PHP runs on `plugins_loaded` at priority 5. Plugin files are already loaded. The theme is not. A snippet can attach to a hook that fires later. It should not call a plugin function, or remove a callback, until that plugin has registered it.
- **Caching friendly** - Files don't change frequently, perfect for caching
- **Zero database overhead** - Snippets stored as PHP files

### Developer Experience
- **In-browser editor** with CodeMirror syntax highlighting
- **Real-time validation** and error checking
- **Enable/disable snippets** without deleting files
- **Template system** for quick snippet creation
- **Comprehensive error handling** and logging

### Security Features
- **Path traversal protection**
- **File validation** before loading
- **Restricted execution** to `.php` files only
- **Error logging** when WP_DEBUG is enabled

## 📦 Installation

1. **Upload** the plugin to `/wp-content/plugins/mc-functionality/`
2. **Activate** the plugin through the WordPress admin
3. **Access** "Site Functions" under the Plugins menu
4. **Create** your first snippet. It is saved in `wp-content/mc-snippets/`

## 🛠️ Quick Start

### Creating Your First Snippet

1. Go to **Plugins → Site Functions** in your WordPress admin
2. Click **"Create New Snippet"**
3. Write your PHP code:

```php
<?php
// Add a custom shortcode
add_shortcode( 'hello_world', function( $atts ) {
    return '<div>Hello from MC Functionality!</div>';
} );

// Hook into WordPress actions
add_action( 'wp_footer', function() {
    echo '<!-- Custom footer content -->';
} );
```

4. **Save** and your snippet is immediately active!

### File-Based Snippets

Place PHP files in `wp-content/mc-snippets/`. That folder sits outside the plugin, so an update does not remove your snippets. Point `MC_FUNCTIONALITY_SNIPPETS_DIR` at another directory if you want them somewhere else.

On WordPress 6.9 or newer, the same files are available through the Abilities API: list, read, create, update, enable, disable, and delete. Administrators receive `read_mc_snippets`, `edit_mc_snippets`, and `delete_mc_snippets` on upgrade. A new file is saved disabled until it is enabled.

```
wp-content/mc-snippets/
├── custom-shortcodes.php
├── theme-modifications.php
├── admin-customizations.php
└── performance-optimizations.php
```

## 🎯 Use Cases

- **Custom shortcodes** and functions
- **Theme modifications** and customizations
- **Admin interface** enhancements
- **Performance optimizations**
- **Security hardening** measures
- **Third-party integrations**

## 🔧 Features

- ✅ **File-based snippet storage**
- ✅ **In-browser code editor**
- ✅ **Enable/disable snippets**
- ✅ **Syntax highlighting**
- ✅ **Error validation**
- ✅ **Template system**
- ✅ **Security protection**
- ✅ **Performance optimized**

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### Reporting Issues
- 🐛 **Bug reports** - Help us improve the plugin
- 💡 **Feature requests** - Suggest new functionality
- 📚 **Documentation** - Help improve our docs

### Submitting Pull Requests
1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Development Guidelines
- Follow **WordPress coding standards**
- Add **proper documentation**
- Include **tests** for new features
- Update **README** if needed

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE.txt](LICENSE.txt) file for details.

## 🙏 Support

- **Documentation**: [Plugin Wiki](https://github.com/mattcromwell/mc-functionality/wiki)
- **Issues**: [GitHub Issues](https://github.com/mattcromwell/mc-functionality/issues)
- **Author**: [Matt Cromwell](https://www.mattcromwell.com/)

## ⭐ Star This Repository

If you find this plugin useful, please consider giving it a star on GitHub. It helps others discover the project!

---

**Built with ❤️ for the WordPress community** 