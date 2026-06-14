# Filecheck — OpenCart Extensions (OC3 & OC4)

Filecheck is a professional file validation and preflight service. It sits in front of an "Upload" button on your e-commerce storefront, validating artwork/files on upload, auto-fixing issues where possible, and showing customers high-quality proofs.

This repository features the complete, self-contained integrations for both **OpenCart 3** and **OpenCart 4**. Both plugins are fully updated to capture upload job IDs, bind them cleanly to orders, and display proof panels and file status workflows right in the OpenCart order admin dashboard.

---

## 🚀 Easy Packaging & Zipping

Because OpenCart expects extensions to follow strict root-level packaging rules, do not zip the outer folders. Instead, compile them using the built-in automated build scripts, or manually package them:

### Method A: One-Click Build Scripts (Recommended)
* **Windows**: Double-click [build.bat](build.bat).
* **Mac/Linux/SSH Console**: Run:
  ```bash
  chmod +x build.sh
  ./build.sh
  ```
* **Result**: Clean, production-ready `.ocmod.zip` extensions are instantly compiled and exported into the newly created `dist/` directory at the project root!

---

### Method B: Manual Zipping

#### OpenCart 3:
1. Open the [oc3/](oc3) directory.
2. Compress the [oc3/upload/](oc3/upload/) folder and [oc3/install.xml](oc3/install.xml).
3. Name your file: `filecheck-oc3.ocmod.zip`.

#### OpenCart 4:
1. Open the [oc4/](oc4) directory.
2. Select all five items directly inside: [oc4/admin/](oc4/admin), [oc4/catalog/](oc4/catalog), [oc4/system/](oc4/system), [oc4/install.json](oc4/install.json), and [oc4/install.xml](oc4/install.xml).
3. Compress those items together. 
4. Name your file: `filecheck-oc4.ocmod.zip`.

---

## ⚙️ Installation

1. Log into your OpenCart Admin Panel.
2. Navigate to **Extensions** → **Extension Installer** (or **Installer** in OC4).
3. Click the **Upload** button and upload your newly compiled `filecheck-ocx.ocmod.zip` extension.
4. Once the upload finishes, click **Install** under the installer history log list.
5. Navigate to **Extensions** → **Extensions**, select **Modules** from the extension type dropdown.
6. Install and open the **Filecheck** module to configure your Publishable Key, Secret Key, and Default Workflow routing.

---

### 🔧 Dynamic Self-Healing Events
The first time you access the **Filecheck** setting configurations screen in your OpenCart Admin interface, the module will dynamically check your database's hooks, automatically register missing triggers, and self-heal any event handler registrations (such as capturing cart additions and mapping checkout orders).

For detailed developer integration specifications and endpoint details, please refer to [INTEGRATION.md](INTEGRATION.md).
