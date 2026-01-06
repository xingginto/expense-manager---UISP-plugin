# UISP Expense Manager Plugin

A comprehensive expense management plugin for UISP that allows you to track, manage, and export your business expenses.

## Features

- **Add Expenses**: Quickly add new expenses with date, description, amount, category, and currency
- **Edit Expenses**: Update existing expense records with an intuitive interface
- **Delete Expenses**: Remove expenses with confirmation dialog
- **CSV Export**: Export all expenses to CSV format for external analysis
- **Pagination**: Handle large numbers of expenses with efficient pagination
- **Categories**: Organize expenses by predefined categories (Office, Travel, Equipment, etc.)
- **Multi-currency**: Support for multiple currencies (USD, EUR, GBP, JPY, CAD, AUD)
- **Responsive Design**: Works seamlessly on desktop and mobile devices

## Installation

1. Copy the entire plugin directory to your UISP plugins folder
2. Navigate to UISP CRM → Settings → Plugins
3. Click "Install Plugin" and select the plugin directory
4. Configure the plugin settings as needed
5. Enable the plugin

## Configuration

The plugin includes the following configurable options:

- **Expenses per page**: Number of expenses to display per page (default: 25)
- **Currency**: Default currency for new expenses (default: USD)
- **Date format**: Date format for expense entries (default: Y-m-d)

## Usage

### Adding Expenses

1. Click the "Add Expense" button
2. Fill in the expense details:
   - Date of the expense
   - Description of what the expense was for
   - Amount spent
   - Category (Office, Travel, Equipment, etc.)
   - Currency
3. Click "Save" to add the expense

### Editing Expenses

1. Click the edit icon (pencil) on any expense card
2. Modify the desired fields
3. Click "Save" to update the expense

### Deleting Expenses

1. Click the delete icon (trash) on any expense card
2. Confirm the deletion in the modal dialog
3. The expense will be permanently removed

### Exporting to CSV

1. Click the "Export CSV" button
2. The CSV file will automatically download with all expense data
3. The file includes all expense fields with timestamps

## File Structure

```
expense-manager/
├── manifest.json          # Plugin metadata and configuration
├── main.php              # Core plugin logic and API endpoints
├── public.php            # Web interface and user experience
├── hook_install.php      # Runs when plugin is installed
├── hook_enable.php       # Runs when plugin is enabled
├── hook_disable.php      # Runs when plugin is disabled
├── hook_update.php       # Runs when plugin is updated
├── hook_remove.php       # Runs when plugin is removed
├── data/                 # Plugin data directory (auto-created)
│   ├── expenses.json     # Expense data storage
│   ├── config.json       # Plugin configuration
│   ├── plugin.log        # Plugin activity log
│   └── files/            # CSV export files
└── README.md             # This documentation
```

## Data Storage

The plugin stores all expense data locally in JSON format within the `data/` directory. This ensures:

- Fast access to expense records
- Easy backup and migration
- No external database dependencies
- Automatic data persistence during plugin updates

## API Endpoints

The plugin provides RESTful API endpoints accessible through the main.php file:

- `GET ?action=get` - Retrieve all expenses (with pagination)
- `GET ?action=get&id={id}` - Retrieve specific expense
- `POST ?action=add` - Create new expense
- `POST ?action=update&id={id}` - Update existing expense
- `POST ?action=delete&id={id}` - Delete expense
- `GET ?action=export` - Export expenses to CSV

## Security

- All data is stored locally within the plugin directory
- No external API calls or data transmission
- Input validation and sanitization
- CSRF protection for form submissions
- Secure file handling for CSV exports

## Browser Compatibility

- Chrome/Chromium 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Support

For issues, feature requests, or contributions, please visit the plugin repository or contact the plugin author.

## License

This plugin is released under the MIT License.
