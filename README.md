# Shoes Shop Management System (Footwear ERP & POS)

A complete Shoes Shop Management System built with **Core PHP, MySQL (PDO), HTML5, CSS3, JavaScript, AJAX, jQuery, Chart.js, and JsBarcode**. Designed specifically for footwear retailers with size and color matrix inventory, customer khata, fast barcode POS billing, supplier purchases, returns/exchanges, daily cash registers, and multi-language support (English & Urdu RTL).

---

## 🌟 Key Features & Shoe-Specific Functionalities

### 1. POS & Fast Billing
- **Instant Barcode Scanning**: Scan any shoe box barcode (e.g. `890101040`) to add the exact size and color to the bill immediately.
- **Dynamic Search & Size Picker**: Search by Article Code (e.g. `ART-101`), Shoe Name, Brand, Size, or Category. Clicking any shoe opens an interactive **Size & Color Matrix Picker** showing live pair counts.
- **Multiple Payment Channels**: Cash, Bank Card, Bank Transfer, JazzCash, Easypaisa, Customer Khata (Credit), and Split Payments.
- **Instant Bill Hold & Resume**: Park customer bills during rush hours with `<F4>` and resume them anytime.
- **Receipt & Invoice Printing**: Automatic browser printing for **80mm / 58mm Thermal Receipts** and **Full A4 Tax Invoices**, complete with barcodes and return policy notes in English and Urdu.
- **Keyboard Shortcuts**:
  - `F1` : Focus Barcode / Search Box
  - `F2` : Open Payment Modal & Complete Sale
  - `F4` : Hold Current Bill
  - `Esc`: Close open modals

### 2. Shoe Inventory & Size Matrix
- **Size-Wise Stock Matrix**: Horizontal size distribution grid (EU Sizes 36–46 and Kids) displaying pair quantities for each shoe model and color.
- **Pair-Based Stock Tracking**: Accurate stock deduction per size; prevents wrong size deductions.
- **Stock Valuation**: Real-time valuation at purchase cost and retail sales value.
- **Low Stock & Out-of-Stock Warnings**: Visual badges (Green = In Stock, Yellow = Low Stock, Red = Out of Stock).
- **Damaged & Defective Tracking**: Dedicated stock status for returned or factory-damaged pairs.
- **Stock Adjustments & Audits**: Record physical audit counts, sample removals, and opening adjustments with complete audit logs.

### 3. Product & Size-Color Management
- **Automatic Matrix Generator**: Create shoe models with article numbers, brands, and categories, then select any combination of colors and sizes to auto-generate all variants with unique SKUs and scannable Code-128 barcodes.
- **Multi-Level Pricing**: Retail price, purchase cost, wholesale price, and minimum selling price.
- **Warehouse Bin Locations**: Rack, Shelf, and Box location tracking.
- **Printable Barcode Stickers**: 50mm x 35mm standard shoe box label generator.

### 4. Purchases & Supplier Management
- **Assortment Purchases**: Receive supplier cartons containing mixed sizes and colors in a single purchase invoice.
- **Landed Costing**: Track freight, carriage, and transportation costs.
- **Supplier Khata & Ledgers**: Chronological statements with purchases, payments, and running balance.

### 5. Customer Management & Khata (Receivables)
- **Customer Profiles**: Record customer phone, address, and **preferred shoe size**.
- **Credit Sales**: Sell on credit (Khata) with configurable credit limits.
- **Customer Ledger**: Printable statement of account showing bills, repayments, and outstanding balances.

### 6. Sales Returns, Exchanges & Refunds
- **Shoe Size & Color Exchanges**: Swap a Size 40 for Size 41 without misrepresenting sales revenue.
- **Price Difference Settlement**: Automatically calculates price differences when exchanging for different models.
- **Condition Grading**: Route returns to resalable stock or defective/damaged inventory.

### 7. Cash Drawer & Financial Management
- **Daily Shift Register**: Morning opening cash, cash sales, cash-in, cash-out, and end-of-day drawer reconciliation.
- **Over/Short Detection**: Identifies physical drawer discrepancies.
- **Expense Tracking**: Shop rent, electricity, salaries, transport, tea, and maintenance.

### 8. True Profit & Analytical Reports
- **Net Operating Profit**: Gross Sales &minus; Variant Purchase Cost (COGS) &minus; Discounts &minus; Returns &minus; Overhead Expenses = **True Net Profit**.
- **Size Popularity Analytics**: Know which shoe sizes sell fastest to optimize factory reorders.
- **Slow-Moving Stock**: Identify stagnant shoes for clearance promotions.
- **One-Click CSV Export**: Export sales data directly to Excel.

### 9. System Configuration & Disaster Recovery
- **English & Urdu (اردو) Support**: Full right-to-left (RTL) layout switcher.
- **One-Click Database Backup**: Download a complete `.sql` dump or restore from backup anytime.

---

## 🚀 How to Access

1. Open your browser and navigate to:
   ```
   http://localhost/Shoes/
   ```

2. **Default Login Credentials**:
   - **Admin / Owner**:
     - Username: `admin`
     - Password: `admin123`
   - **Store Manager**:
     - Username: `manager`
     - Password: `admin123`
   - **Cashier**:
     - Username: `cashier`
     - Password: `admin123`

---

## 📁 Directory Structure

```
c:\wamp64\www\Shoes\
├── config/
│   ├── database.php        # PDO connection (shoes_db)
│   ├── functions.php       # Auth, helpers, audit logs, currency formatting
│   └── lang/
│       ├── en.php          # English dictionary
│       └── ur.php          # Urdu (اردو) dictionary
├── includes/
│   ├── auth_check.php      # Route security & permission guard
│   ├── header.php          # Responsive top navigation & RTL handler
│   └── footer.php          # Shortcut handlers & library scripts
├── assets/
│   └── css/style.css       # Custom shoe POS & responsive styles
├── schema.sql              # Complete MySQL database schema & seed data
├── index.php               # Gateway router
├── login.php & logout.php  # Authentication
├── dashboard.php           # Real-time KPI cards & sales charts
├── pos.php                 # Fast POS screen & barcode reader
├── pos_actions.php         # AJAX API endpoints for POS transactions
├── invoice_print.php       # Thermal (80mm) & A4 invoice printing
├── products.php            # Shoes catalog
├── product_add.php         # Size-color matrix auto-generator
├── product_edit.php        # Model & variant editor
├── inventory.php           # Article-wise size matrix & stock valuation
├── stock_adjust.php        # Stock-in, stock-out, and physical audits
├── purchases.php           # Supplier purchase invoices
├── purchase_add.php        # Assortment matrix receiving
├── suppliers.php           # Supplier directory & payment settlement
├── supplier_ledger.php     # Supplier Khata statement
├── customers.php           # Customer directory & shoe size preferences
├── customer_ledger.php     # Customer Khata statement
├── returns.php             # Sales returns & size exchange log
├── return_create.php       # Size exchange & difference processor
├── expenses.php            # Overhead expenses
├── cash_register.php       # Daily cash drawer & reconciliation
├── reports.php             # Net profit, size analytics & CSV export
├── barcode_labels.php      # Shoe box barcode label generator
├── users.php               # Staff accounts & role permissions
├── settings.php            # Shop profile, policy & printer setup
└── backup.php              # One-click SQL backup & restore
```
