# PROPOSAL Revision Plan and Recommended Wording

## Main Revision Goal

Revise the proposal so Chapter 1 clearly matches the actual PrintEase system, and Chapter 3 lists the specific technologies, tools, and versions used in development.

## Chapter 1: Scope and Limitations

### What to Improve

- Keep the current scope because it already covers the main system features.
- Add the Super Admin role because the system includes a super administrator module.
- Add limited offline/PWA support only as a limited feature, not full offline mode.
- Make the limitations clearer by explaining what cannot work without internet.
- Make sure the payment limitation matches the actual system, especially if the system uses GCash payment proof upload instead of a fully automated payment gateway.

### Recommended Scope Wording

The Mobile and Web-Based E-Printing System for Print Shops in Calbayog City is developed for customers, print shop owners, and the system super administrator within Calbayog City. The system aims to improve and digitize the traditional printing process by providing separate platforms based on the needs of each user role.

The customer side is designed as a mobile-based platform through Progressive Web App technology. Customers can access the system using a web browser and may install or add the website to their mobile device for easier access, similar to a mobile application. Through this customer platform, users can browse available print shops, upload documents, select print options, place printing orders, view shop details, locate shops through a map feature, monitor order status, receive notifications, and submit online payment proof.

The print shop owner side is web-based and provides a dashboard where shop owners can manage shop profiles, services, pricing, customer orders, payment verification, transactions, notifications, and reports. Print shop owners can also update the current status of their shop, such as available, busy, or not accepting orders.

The super administrator side is also web-based and allows the management and monitoring of users, print shops, shop approvals, activity logs, and platform reports. This ensures that the system can be properly controlled and monitored by the authorized administrator.

### Recommended Limitations Wording

However, the system has several limitations. Although the customer platform can be installed on a mobile device through Progressive Web App support, it is not a native mobile application from an app store. Its main functions still depend on a stable internet connection, especially document uploading, order submission, real-time order updates, payment proof submission, and data synchronization. The offline feature is limited only to cached or previously loaded pages and does not allow users to place new orders, upload files, process payments, or receive real-time updates while offline.

The accuracy of print shop availability depends on manual updates by the print shop owners and may not always reflect the actual workload of the shop. The system is also limited to print shops within Calbayog City and does not support shops outside the target location. In addition, the system does not include delivery services, cash-on-pickup transactions, automatic file editing, or automatic document error detection. These limitations are due to time constraints, technical constraints, and available resources during system development.

## Chapter 3: Technical Background

### Main Reminder

Revise only **Chapter 3 Table 1** and **Chapter 3 Table 2**.

- Table 1 should be for **Software Requirements**.
- Table 2 should be for **Hardware Requirements**.
- Do not add a separate Technical Requirements table if your adviser only requires Table 1 and Table 2.

Use this format:

| Category | Specification | Description |
|---|---|---|

In this format, the **Category** column should contain the actual software, technology, library, tool, or hardware item. The **Specification** column should contain the version or exact specification used.

## Table 1: Software Requirements

Use only the main technologies used to develop and run the system.

| Category | Specification | Description |
|---|---|---|
| PHP | Version 8.5.7 | Used for server-side processing, authentication, order handling, and database connection. |
| JavaScript | ES6 / Vanilla JavaScript | Used for client-side interactions, form validation, notifications, and live updates. |
| HTML | HTML5 | Used to structure the system pages and user interfaces. |
| CSS | CSS3 with Tailwind CSS v4.3.2 | Used for styling, layout, and responsive design. |
| MySQL | MySQL Database | Used to store user accounts, shop details, orders, payments, notifications, and activity logs. |
| Apache / Laragon or XAMPP | Local web server environment | Used to run and test the PHP-based web system during development. |
| Progressive Web App | Web App Manifest and Service Worker | Used to allow customers to install the website on a mobile device and access cached pages. |
| Visual Studio Code | Latest available IDE version | Used for coding, debugging, and managing project files. |
| Google Chrome / Microsoft Edge | Latest available browser version | Used for system testing and browser compatibility checking. |

### Optional Only If Adviser Wants More Details

Add these only if your adviser asks for libraries or services used:

| Category | Specification | Description |
|---|---|---|
| Cloudinary PHP | Version 3.1.3 | Used for cloud storage of uploaded files. |
| PHPMailer | Version 7.1.1 | Used for sending OTP and account verification emails. |
| Google API Client | Version 2.19.3 | Used for Google OAuth sign-in. |

### Paragraph After Table 1

Table 1 presents the main software requirements used in developing the Mobile and Web-Based E-Printing System for Print Shops in Calbayog City. The system uses PHP for server-side processes and JavaScript for client-side interactions. HTML, CSS, and Tailwind CSS are used to create and style the user interfaces, while MySQL is used to store and manage system data. The system also uses Apache through Laragon or XAMPP as the local web server environment and Progressive Web App technology for the customer-side mobile-based access.

## Table 2: Hardware Requirements

Use only the actual hardware needed for development and testing.

| Category | Specification | Description |
|---|---|---|
| Laptop / Desktop Computer | Intel Core i5 processor, 8GB RAM, and 1TB storage | Used for system development, coding, database management, testing, debugging, and running the local server environment. |
| Mobile Phone | Octa-core processor, 6GB or 8GB RAM, and 128GB or 256GB storage | Used for testing the customer-side mobile-based PWA, including responsiveness, installation, navigation, order placement, and file upload. |
| Internet Connection | Stable Wi-Fi or mobile data connection | Used for testing online features such as file upload, notifications, payment proof submission, Google sign-in, Cloudinary storage, and data synchronization. |
| Printer | Standard document printer | Used for testing the print shop workflow if actual printing output was tested during development. Include this row only if you really used a printer. |

### Paragraph After Table 2

Table 2 presents the hardware requirements used in the development and testing of the Mobile and Web-Based E-Printing System for Print Shops in Calbayog City. A laptop or desktop computer was used for coding, database management, debugging, and running the local ser`ver environment. A mobile phone was used to test the customer-side Progressive Web App, ensuring that the system can be accessed, installed, and used properly on mobile devices. A stable internet connection was also required to test online features such as file uploading, notifications, payment proof submission, Google sign-in, cloud storage, and data synchronization. These hardware requirements helped ensure that the system was properly developed and tested for its intended users.

## Specific Notes to Check Before Finalizing

- Confirm if the actual system is a PWA/web app or a React Native mobile app.
- If Bootstrap is no longer used, remove it from Chapter 3.
- If Tailwind CSS is used, include Tailwind CSS v4.3.2.
- If the payment is not a real payment gateway, avoid saying "payment gateway"; use "GCash payment proof upload and verification" instead.
- If offline mode only shows cached pages, call it "limited offline support" instead of full offline mode.
- Make sure Chapter 1, Chapter 3, and the actual system features use the same terms.
- Do not use broad labels like "Programming Languages" as a Category in the Chapter 3 software table; use the actual tool name instead.
- Keep Table 1 short and important.
- Do not include React Native or Bootstrap unless they are actually used in the final system.
- Exclude less essential libraries like Predis, Tesseract OCR Wrapper, and FPDI unless your adviser asks for complete technical details.
- Add a printer only if actual printer testing was performed.

## Quick Revision Checklist

- [ ] Chapter 1 includes customer, print shop owner, and super admin.
- [ ] Scope explains that the customer side is mobile-based through an installable PWA.
- [ ] Scope explains that the print shop owner and super admin sides are web-based dashboards.
- [ ] Scope includes upload, print options, shop selection, map, tracking, notifications, payment proof, and reports.
- [ ] Limitations mention internet dependency and limited offline support.
- [ ] Limitations clearly say offline mode cannot upload, order, pay, or sync.
- [ ] Chapter 3 lists actual technologies and versions.
- [ ] React Native/Bootstrap are removed if they are not actually used.
- [ ] PHP, MySQL, Tailwind CSS, PWA, Cloudinary, PHPMailer, Google API Client, Predis, and Tesseract OCR are included if they are part of the final system.
- [ ] Spelling and grammar are corrected: "Programming", "Database Server", "ensures", "customers".
