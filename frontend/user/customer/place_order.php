<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

$shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;

$shop_sql = "SELECT * FROM print_shops 
             WHERE shop_id = ? 
             AND permit_status = 'verified'
             LIMIT 1";

$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $shop_id);
mysqli_stmt_execute($shop_stmt);
$shop_result = mysqli_stmt_get_result($shop_stmt);
$shop = mysqli_fetch_assoc($shop_result);

if (!$shop) {
    setError("Invalid or unverified print shop.");
    header("Location: explore.php?view=all");
    exit();
}

if (($shop['shop_status'] ?? 'not_accepting') === 'not_accepting') {
    setToast("This print shop is currently not accepting requests.", "warning");
    header("Location: explore.php?view=all");
    exit();
}

$service_sql = "SELECT * FROM shop_services 
                WHERE shop_id = ? 
                AND is_available = 1
                ORDER BY paper_size ASC, print_type ASC";

$service_stmt = mysqli_prepare($conn, $service_sql);
mysqli_stmt_bind_param($service_stmt, "i", $shop_id);
mysqli_stmt_execute($service_stmt);
$services = mysqli_stmt_get_result($service_stmt);

$service_list = [];
while ($row = mysqli_fetch_assoc($services)) {
    $service_list[] = $row;
}

if (empty($service_list)) {
    setToast("This shop has no available services yet.", "warning");
    header("Location: explore.php?view=all");
    exit();
}

$pricing_sql = "SELECT spp.id, spp.service_type, spp.option_size, spp.option_label, spp.unit, spp.price
                FROM shop_service_pricing spp
                INNER JOIN shop_service_types sst
                    ON sst.shop_id = spp.shop_id
                    AND sst.service_type = spp.service_type
                WHERE spp.shop_id = ?
                AND spp.is_available = 1
                AND sst.service_offered = 1
                AND sst.online_available = 1
                AND spp.service_type IN ('Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing')
                ORDER BY spp.service_type ASC, spp.option_size ASC, spp.option_label ASC, spp.unit ASC";
$pricing_stmt = mysqli_prepare($conn, $pricing_sql);
$service_pricing_list = [];
if ($pricing_stmt) {
    mysqli_stmt_bind_param($pricing_stmt, "i", $shop_id);
    mysqli_stmt_execute($pricing_stmt);
    $pricing_result = mysqli_stmt_get_result($pricing_stmt);
    while ($row = mysqli_fetch_assoc($pricing_result)) {
        $service_pricing_list[] = $row;
    }
}

$allowed_service_types = ['Document Printing', 'Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing', 'Photocopy', 'Binding', 'Scanning'];
$shop_service_availability = [
    'Document Printing' => [
        'service_type' => 'Document Printing',
        'online_available' => true,
        'customer_note' => 'Upload your file and configure your printing requirements.',
    ],
];
$service_type_sql = "SELECT service_type, online_available, customer_note
                     FROM shop_service_types
                     WHERE shop_id = ?
                     AND service_offered = 1
                     ORDER BY service_type ASC";
$service_type_stmt = mysqli_prepare($conn, $service_type_sql);
if ($service_type_stmt) {
    mysqli_stmt_bind_param($service_type_stmt, "i", $shop_id);
    mysqli_stmt_execute($service_type_stmt);
    $service_type_result = mysqli_stmt_get_result($service_type_stmt);
    while ($service_type_row = mysqli_fetch_assoc($service_type_result)) {
        $type = trim((string) ($service_type_row['service_type'] ?? ''));
        if ($type === '' || !in_array($type, $allowed_service_types, true)) {
            continue;
        }
        $shop_service_availability[$type] = [
            'service_type' => $type,
            'online_available' => $type === 'Document Printing' ? true : ((int) ($service_type_row['online_available'] ?? 1) === 1),
            'customer_note' => (string) ($service_type_row['customer_note'] ?? ''),
        ];
    }
}
$customer_service_types = array_values(array_keys($shop_service_availability));

if (empty($_SESSION['order_submit_token'])) {
    $_SESSION['order_submit_token'] = bin2hex(random_bytes(16));
}
$order_submit_token = $_SESSION['order_submit_token'];

date_default_timezone_set('Asia/Manila');
$min_pickup = date('Y-m-d\TH:i');
$shop_logo_file = trim((string) ($shop['shop_logo'] ?? ''));
$shop_logo_url = $shop_logo_file !== '' ? SHOP_LOGOS_URL . basename($shop_logo_file) : '';
$shop_is_busy = ($shop['shop_status'] ?? '') === 'busy';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Place Request</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <script src="<?php echo printEaseAssetUrl('assets/js/pdf.min.js'); ?>"></script>
    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
        window.PRINTEASE_PDFJS_LOCAL = typeof window.pdfjsLib !== 'undefined';
        if (!window.PRINTEASE_PDFJS_LOCAL) {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"><\/script>');
        }
    </script>
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-5xl mx-auto min-h-screen">

        <?php renderCustomerLayout(['title' => 'Place Request', 'subtitle' => 'Requesting from ' . $shop['shop_name']]); ?>

        <main class="p-4 md:p-6">
            <form method="GET" data-live-search-form data-live-target="customer_place_order" hidden aria-hidden="true">
                <input type="hidden" name="shop_id" value="<?php echo e($shop_id); ?>">
            </form>
            <div data-live-region="customer-place-order-data" hidden
                data-services-json="<?php echo e(json_encode($service_list)); ?>"
                data-service-prices-json="<?php echo e(json_encode($service_pricing_list)); ?>"
                data-service-availability-json="<?php echo e(json_encode($shop_service_availability)); ?>"
                data-service-types-json="<?php echo e(json_encode($customer_service_types)); ?>"></div>
            <form action="../../../backend/actions/submit_order.php" method="POST" enctype="multipart/form-data"
                class="customer-order-wizard bg-white p-5 md:p-6 rounded-2xl shadow" data-order-wizard novalidate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="order_submit_token" value="<?php echo e($order_submit_token); ?>">
                <input type="hidden" name="shop_id" value="<?php echo e($shop['shop_id']); ?>">
                <input type="hidden" name="order_service_type" id="order_service_type" value="Document Printing">
                <input type="hidden" name="service_id" id="service_id">
                <input type="hidden" name="service_pricing_id" id="service_pricing_id">
                <input type="hidden" name="detected_page_count" id="detected_page_count" value="1">

                <ol class="customer-order-steps" aria-label="Request progress">
                    <li class="is-active" data-step-indicator="0"><span>1</span><strong>Service</strong></li>
                    <li data-step-indicator="1"><span>2</span><strong>Settings</strong></li>
                    <li data-step-indicator="2"><span>3</span><strong>Pickup</strong></li>
                    <li data-step-indicator="3"><span>4</span><strong>Review</strong></li>
                </ol>

                <p class="customer-order-wizard-alert" data-wizard-alert role="alert" hidden></p>

                <?php if ($shop_is_busy): ?>
                    <section class="customer-busy-shop-inline" role="status">
                        <strong>Notice: This shop is currently busy.</strong>
                        <span>This shop is in high demand and has many requests at the moment, so your request may take longer than usual.</span>
                    </section>
                <?php endif; ?>

                <section class="customer-order-step is-active" data-step-panel="0" aria-labelledby="orderStepFile">
                    <div class="customer-order-step-head">
                        <span>Step 1 of 4</span>
                        <h2 id="orderStepFile">Choose service</h2>
                        <p>Select what you want to request from this shop.</p>
                    </div>

                    <div class="customer-order-shop-summary">
                        <div class="customer-order-shop-icon">
                            <?php if ($shop_logo_url !== ''): ?>
                                <img src="<?php echo e($shop_logo_url); ?>"
                                    alt="<?php echo e($shop['shop_name']); ?> logo">
                            <?php else: ?>
                                <?php echo customerIcon('printer'); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <small>Selected shop</small>
                            <strong><?php echo e($shop['shop_name']); ?></strong>
                            <span><?php echo e($shop['shop_address'] ?? 'Shop address not provided'); ?></span>
                        </div>
                    </div>

                    <label class="customer-order-field">
                        <span>Service</span>
                        <select name="customer_service_type" id="customer_service_type" required
                            class="w-full border rounded-xl p-3">
                            <?php foreach ($customer_service_types as $type): ?>
                                <?php
                                $online_available = !empty($shop_service_availability[$type]['online_available']);
                                $suffix = $online_available ? '' : ' - Shop visit required';
                                ?>
                                <option value="<?php echo e($type); ?>"><?php echo e($type . $suffix); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="customer-service-visit-notice" data-service-visit-notice hidden>
                        <strong>This service requires a shop visit.</strong>
                        <span data-service-visit-note>Please contact or visit the shop to proceed.</span>
                        <small><?php echo e(trim((string) ($shop['shop_address'] ?? '')) !== '' ? $shop['shop_address'] : 'Shop address not provided'); ?></small>
                    </div>

                    <label class="customer-order-field" data-document-upload-field>
                        <span>Upload Document</span>
                        <input type="file" name="document_file" id="document_file" accept="application/pdf,.pdf" required
                            class="w-full border rounded-xl p-3">
                        <small>Each request must contain exactly one file attachment only.
                            Only PDF files up to 25MB are accepted.
                        </small>
                    </label>

                    <label class="customer-order-field" data-other-upload-field hidden>
                        <span>Upload File</span>
                        <input type="file" name="service_file" id="service_file" accept="application/pdf,.pdf,image/png,image/jpeg,image/jpg" required
                            class="w-full border rounded-xl p-3">
                        <small>Required for this request. PDF, JPG, and PNG files up to 25MB are accepted.</small>
                    </label>
                </section>

                <section class="customer-order-step" data-step-panel="1" aria-labelledby="orderStepSettings" hidden>
                    <div class="customer-order-step-head">
                        <span>Step 2 of 4</span>
                        <h2 id="orderStepSettings">Choose print settings</h2>
                        <p data-settings-copy>Select the service options available from this shop.</p>
                    </div>

                    <div class="customer-order-grid" data-document-settings>
                        <label class="customer-order-field">
                            <span>Paper Size</span>
                            <select id="paper_size" required class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span>Paper Type</span>
                            <select id="paper_type" required class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span>Print Type</span>
                            <select id="print_type" required class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span>Copies</span>
                            <input type="number" name="copies" id="copies" min="1" value="1" required
                                class="w-full border rounded-xl p-3">
                        </label>

                        <div class="customer-order-field">
                            <span>Pages</span>
                            <strong class="w-full border rounded-xl p-3 bg-gray-50 block"
                                data-page-count-label>1</strong>
                            <small data-page-count-status>Upload a PDF to detect pages automatically.</small>
                        </div>
                    </div>

                    <div class="customer-order-grid" data-other-settings hidden>
                        <label class="customer-order-field">
                            <span>Size</span>
                            <select id="service_size" class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span data-service-material-label>Material</span>
                            <select id="service_material" class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span>Print Type</span>
                            <select id="service_print_type" class="w-full border rounded-xl p-3"></select>
                        </label>

                        <label class="customer-order-field">
                            <span>Quantity</span>
                            <input type="number" name="service_quantity" id="service_quantity" min="1" value="1"
                                class="w-full border rounded-xl p-3">
                        </label>

                        <div class="customer-order-field" data-service-basis-field hidden>
                            <span>Pricing Basis</span>
                            <strong class="w-full border rounded-xl p-3 bg-gray-50 block"
                                data-service-basis>Choose an option</strong>
                        </div>
                    </div>

                    <div class="customer-service-visit-panel" data-visit-required-settings hidden>
                        <strong>This service requires a shop visit.</strong>
                        <p data-visit-required-copy>Please contact or visit the shop to proceed.</p>
                        <span><?php echo e(trim((string) ($shop['shop_address'] ?? '')) !== '' ? $shop['shop_address'] : 'Shop address not provided'); ?></span>
                    </div>

                    <div class="customer-order-total">
                        <span>Estimated Total</span>
                        <strong>&#8369;<span id="total">0.00</span></strong>
                        <small class="block text-sm text-gray-700 mt-1" data-paper-price>Price: &#8369;0.00/page</small>
                        <small class="block text-sm text-gray-500 mt-1" data-total-breakdown>0 pages x 1 copy</small>
                    </div>
                </section>

                <section class="customer-order-step" data-step-panel="2" aria-labelledby="orderStepPickup" hidden>
                    <div class="customer-order-step-head">
                        <span>Step 3 of 4</span>
                        <h2 id="orderStepPickup">Schedule pickup</h2>
                        <p>Pick a future pickup schedule and add optional instructions.</p>
                    </div>

                    <div class="customer-order-grid">
                        <label class="customer-order-field">
                            <span>Pickup Date and Time</span>
                            <input type="datetime-local" name="pickup_datetime" id="pickup_datetime"
                                min="<?php echo $min_pickup; ?>" required class="w-full border rounded-xl p-3">
                        </label>

                        <label class="customer-order-field">
                            <span>Instruction</span>
                            <textarea name="customer_instruction" id="customer_instruction" rows="4"
                                placeholder="Example: Please print back-to-back."
                                class="w-full border rounded-xl p-3"></textarea>
                        </label>
                    </div>
                </section>

                <section class="customer-order-step" data-step-panel="3" aria-labelledby="orderStepReview" hidden>
                    <div class="customer-order-step-head">
                        <span>Step 4 of 4</span>
                        <h2 id="orderStepReview">Review request</h2>
                        <p>Check the details before submitting your print request.</p>
                    </div>

                    <div class="customer-order-review">
                        <div><span>Shop</span><strong><?php echo e($shop['shop_name']); ?></strong></div>
                        <div><span>Service</span><strong data-review-service>Document Printing</strong></div>
                        <div><span>Attachment</span><strong data-review-file>Not selected</strong></div>
                        <div><span data-review-paper-size-label>Paper Size</span><strong data-review-paper-size>-</strong></div>
                        <div data-review-paper-type-row><span data-review-paper-type-label>Paper Type</span><strong data-review-paper-type>-</strong></div>
                        <div data-review-print-type-row><span data-review-print-type-label>Print Type</span><strong data-review-print-type>-</strong></div>
                        <div data-review-pricing-basis-row><span>Pricing Basis</span><strong data-review-pricing-basis>Per Page</strong></div>
                        <div><span>Unit Price</span><strong data-review-paper-price>&#8369;0.00/page</strong></div>
                        <div><span data-review-pages-label>Pages</span><strong data-review-pages>1</strong></div>
                        <div><span>Copies</span><strong data-review-copies>1</strong></div>
                        <div><span>Pickup</span><strong data-review-pickup>-</strong></div>
                        <div><span>Instructions</span><strong data-review-instruction>None</strong></div>
                    </div>

                    <div class="customer-order-total review-total">
                        <span>Total Amount</span>
                        <strong>&#8369;<span data-review-total>0.00</span></strong>
                        <small class="block text-sm text-gray-500 mt-1" data-review-breakdown>1 page x 1 copy</small>
                    </div>
                </section>

                <div class="customer-order-actions">
                    <a href="explore.php?view=all" class="customer-order-cancel" data-wizard-cancel>Cancel</a>
                    <button type="button" class="customer-order-back" data-wizard-back hidden>Back</button>
                    <button type="button" class="customer-order-next bg-blue-600 text-white"
                        data-wizard-next>Next</button>
                    <button type="submit" name="submit_order" class="customer-order-submit bg-blue-600 text-white"
                        data-wizard-submit hidden>
                        Submit Request
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
        let services = <?php echo json_encode($service_list); ?>;
        let servicePrices = <?php echo json_encode($service_pricing_list); ?>;
        let serviceAvailability = <?php echo json_encode($shop_service_availability); ?>;

        const wizard = document.querySelector("[data-order-wizard]");
        const indicators = Array.from(document.querySelectorAll("[data-step-indicator]"));
        const panels = Array.from(document.querySelectorAll("[data-step-panel]"));
        const alertBox = document.querySelector("[data-wizard-alert]");
        const backButton = document.querySelector("[data-wizard-back]");
        const nextButton = document.querySelector("[data-wizard-next]");
        const submitButton = document.querySelector("[data-wizard-submit]");
        const cancelLink = document.querySelector("[data-wizard-cancel]");
        const customerServiceType = document.getElementById("customer_service_type");
        const orderServiceType = document.getElementById("order_service_type");
        const documentFile = document.getElementById("document_file");
        const serviceFile = document.getElementById("service_file");
        const documentUploadField = document.querySelector("[data-document-upload-field]");
        const otherUploadField = document.querySelector("[data-other-upload-field]");
        const serviceVisitNotice = document.querySelector("[data-service-visit-notice]");
        const serviceVisitNote = document.querySelector("[data-service-visit-note]");
        const documentSettings = document.querySelector("[data-document-settings]");
        const otherSettings = document.querySelector("[data-other-settings]");
        const visitRequiredSettings = document.querySelector("[data-visit-required-settings]");
        const visitRequiredCopy = document.querySelector("[data-visit-required-copy]");
        const settingsCopy = document.querySelector("[data-settings-copy]");
        const serviceSize = document.getElementById("service_size");
        const serviceMaterial = document.getElementById("service_material");
        const servicePrintType = document.getElementById("service_print_type");
        const serviceMaterialLabel = document.querySelector("[data-service-material-label]");
        const servicePricingId = document.getElementById("service_pricing_id");
        const serviceQuantity = document.getElementById("service_quantity");
        const serviceBasisField = document.querySelector("[data-service-basis-field]");
        const serviceBasis = document.querySelector("[data-service-basis]");
        const paperType = document.getElementById("paper_type");
        const paperSize = document.getElementById("paper_size");
        const printType = document.getElementById("print_type");
        const copies = document.getElementById("copies");
        const pickupDatetime = document.getElementById("pickup_datetime");
        const instruction = document.getElementById("customer_instruction");
        const total = document.getElementById("total");
        const serviceId = document.getElementById("service_id");
        const detectedPageCount = document.getElementById("detected_page_count");
        const pageCountLabel = document.querySelector("[data-page-count-label]");
        const pageCountStatus = document.querySelector("[data-page-count-status]");
        const paperPrice = document.querySelector("[data-paper-price]");
        const totalBreakdown = document.querySelector("[data-total-breakdown]");
        const reviewBreakdown = document.querySelector("[data-review-breakdown]");
        const maxDocumentFileSize = 25 * 1024 * 1024;
        const maxServiceFileSize = 25 * 1024 * 1024;
        let currentStep = 0;
        let pageCountLoading = false;
        let pageCountRequestId = 0;
        let pageCountParseFailed = false;
        let serviceFileParseFailed = false;
        let serviceFileVerifying = false;
        let formSubmitting = false;

        function readOrderLiveData() {
            const source = document.querySelector('[data-live-region="customer-place-order-data"]');
            if (!source) return null;
            try {
                return {
                    services: JSON.parse(source.dataset.servicesJson || "[]"),
                    servicePrices: JSON.parse(source.dataset.servicePricesJson || "[]"),
                    serviceTypes: JSON.parse(source.dataset.serviceTypesJson || "[]")
                };
            } catch (error) {
                return null;
            }
        }

        function applyOrderLiveData() {
            if (!wizard || wizard.dataset.liveDirty === "true") return;
            const data = readOrderLiveData();
            if (!data) return;

            services = Array.isArray(data.services) ? data.services : [];
            servicePrices = Array.isArray(data.servicePrices) ? data.servicePrices : [];

            if (customerServiceType && Array.isArray(data.serviceTypes)) {
                const currentType = customerServiceType.value || "Document Printing";
                customerServiceType.innerHTML = data.serviceTypes.map(type =>
                    `<option value="${escapeOptionValue(type)}">${type}</option>`
                ).join("");
                customerServiceType.value = data.serviceTypes.includes(currentType) ? currentType : (data.serviceTypes[0] || "Document Printing");
            }

            syncServiceMode();
        }

        if (wizard) {
            wizard.addEventListener("input", function () {
                wizard.dataset.liveDirty = "true";
            }, { once: true });
            wizard.addEventListener("change", function () {
                wizard.dataset.liveDirty = "true";
            }, { once: true });
        }

        window.addEventListener("printease:live-regions-replaced", function (event) {
            const regions = event.detail && event.detail.regions ? event.detail.regions : [];
            if (regions.includes("customer-place-order-data")) {
                applyOrderLiveData();
            }
        });

        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = window.PRINTEASE_PDFJS_LOCAL
                ? <?php echo json_encode(printEaseAssetUrl('assets/js/pdf.worker.min.js')); ?>
                : "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
        }

        function setPageCount(count, statusText = "") {
            const normalized = Math.max(1, parseInt(count || "1", 10) || 1);
            detectedPageCount.value = String(normalized);
            if (pageCountLabel) pageCountLabel.textContent = String(normalized);
            if (pageCountStatus) pageCountStatus.textContent = statusText || (normalized === 1 ? "1 page will be charged." : normalized + " pages will be charged.");
            computeTotal();
        }

        function selectedFileIsPdf(file) {
            if (!file) return false;
            return file.type === "application/pdf" || /\.pdf$/i.test(file.name || "");
        }

        function isDocumentOrder() {
            return !customerServiceType || customerServiceType.value === "Document Printing";
        }

        function selectedAvailability() {
            const type = customerServiceType ? customerServiceType.value : "Document Printing";
            const details = serviceAvailability && serviceAvailability[type] ? serviceAvailability[type] : {};
            return {
                online: details.online_available !== false,
                note: details.customer_note || ""
            };
        }

        function isShopVisitOnlyOrder() {
            return !selectedAvailability().online;
        }

        function isPhotoPrintingOrder() {
            return customerServiceType && customerServiceType.value === "Photo Printing";
        }

        function isTarpaulinPrintingOrder() {
            return customerServiceType && customerServiceType.value === "Tarpaulin Printing";
        }

        function isIdPrintingOrder() {
            return customerServiceType && customerServiceType.value === "ID Printing";
        }

        function isInvitationCardPrintingOrder() {
            return customerServiceType && customerServiceType.value === "Invitation / Card Printing";
        }

        function isDetailedServiceOrder() {
            return isPhotoPrintingOrder() || isTarpaulinPrintingOrder() || isIdPrintingOrder() || isInvitationCardPrintingOrder();
        }

        function validateDocumentFile(file) {
            if (!file) {
                return "Please upload your document before continuing.";
            }
            if (file.size > maxDocumentFileSize) {
                return "Document file must be 25MB or smaller.";
            }
            if (!selectedFileIsPdf(file)) {
                return "Only PDF files are accepted for print requests.";
            }
            return "";
        }

        function selectedServiceFileIsAllowed(file) {
            if (!file) return true;
            return file.type === "application/pdf" ||
                file.type === "image/jpeg" ||
                file.type === "image/png" ||
                /\.(pdf|jpe?g|png)$/i.test(file.name || "");
        }

        function validateServiceFile(file) {
            if (!file) {
                return "Please upload a file for this service request.";
            }
            if (file.size > maxServiceFileSize) {
                return "Attachment must be 25MB or smaller.";
            }
            if (!selectedServiceFileIsAllowed(file)) {
                return "Attachment must be a PDF, JPG, or PNG file.";
            }
            return "";
        }

        function pdfFileLooksValid(file) {
            return file.slice(0, 4).text().then(function (head) {
                return head === "%PDF";
            }).catch(function () {
                return false;
            });
        }

        function verifyImageFile(file) {
            return new Promise(function (resolve, reject) {
                if (typeof createImageBitmap === "function") {
                    createImageBitmap(file).then(function () {
                        resolve();
                    }, function () {
                        reject(new Error("invalid image"));
                    });
                    return;
                }
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = function () {
                    URL.revokeObjectURL(url);
                    resolve();
                };
                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error("invalid image"));
                };
                img.src = url;
            });
        }

        async function detectServiceFile() {
            serviceFileParseFailed = false;
            serviceFileVerifying = false;
            if (!serviceFile) return;
            const file = serviceFile.files && serviceFile.files[0] ? serviceFile.files[0] : null;
            if (!file) return;
            if (validateServiceFile(file)) return;

            const isPdf = file.type === "application/pdf" || /\.pdf$/i.test(file.name || "");
            const isImage = file.type === "image/jpeg" || file.type === "image/png" || /\.(jpe?g|png)$/i.test(file.name || "");
            if (!isPdf && !isImage) return;

            serviceFileVerifying = true;
            try {
                if (isPdf) {
                    if (window.pdfjsLib && navigator.onLine !== false) {
                        const buffer = await file.arrayBuffer();
                        await pdfjsLib.getDocument({ data: buffer }).promise;
                        serviceFileParseFailed = false;
                    } else {
                        serviceFileParseFailed = !(await pdfFileLooksValid(file));
                    }
                } else if (isImage) {
                    await verifyImageFile(file);
                    serviceFileParseFailed = false;
                }
            } catch (error) {
                if (isPdf && navigator.onLine === false) {
                    serviceFileParseFailed = !(await pdfFileLooksValid(file));
                } else {
                    serviceFileParseFailed = true;
                }
            } finally {
                serviceFileVerifying = false;
            }
        }

        async function detectDocumentPages() {
            const file = documentFile.files && documentFile.files[0] ? documentFile.files[0] : null;
            const requestId = ++pageCountRequestId;
            pageCountParseFailed = false;

            if (!file) {
                pageCountLoading = false;
                setPageCount(1, "Upload a PDF to detect pages automatically.");
                return;
            }

            const fileValidationMessage = validateDocumentFile(file);
            if (fileValidationMessage) {
                pageCountLoading = false;
                setPageCount(1, fileValidationMessage);
                return;
            }

            if (!window.pdfjsLib || navigator.onLine === false) {
                pageCountLoading = false;
                const looksValid = await pdfFileLooksValid(file);
                if (looksValid) {
                    setPageCount(1, "Page count will be verified when your request is sent.");
                } else {
                    pageCountParseFailed = true;
                    setPageCount(1, "This does not look like a valid PDF file.");
                }
                return;
            }

            pageCountLoading = true;
            if (pageCountStatus) pageCountStatus.textContent = "Counting PDF pages...";
            if (pageCountLabel) pageCountLabel.textContent = "...";
            computeTotal();

            try {
                const buffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;
                if (requestId !== pageCountRequestId) return;
                pageCountLoading = false;
                setPageCount(pdf.numPages || 1, (pdf.numPages || 1) + " PDF page" + ((pdf.numPages || 1) === 1 ? "" : "s") + " detected.");
            } catch (error) {
                if (requestId !== pageCountRequestId) return;
                pageCountLoading = false;
                if (navigator.onLine === false) {
                    const looksValid = await pdfFileLooksValid(file);
                    if (looksValid) {
                        pageCountParseFailed = false;
                        setPageCount(1, "Page count will be verified when your request is sent.");
                    } else {
                        pageCountParseFailed = true;
                        setPageCount(1, "This does not look like a valid PDF file.");
                    }
                } else {
                    pageCountParseFailed = true;
                    setPageCount(1, "Could not read PDF pages. 1 page will be used.");
                }
            }
        }

        function uniqueValues(key, filter = {}) {
            return [...new Set(services.filter(s =>
                Object.keys(filter).every(k => s[k] === filter[k])
            ).map(s => s[key]))];
        }

        function escapeOptionValue(value) {
            return String(value).replaceAll("&", "&amp;").replaceAll('"', "&quot;");
        }

        function fillSelect(select, values) {
            select.innerHTML = values.map(v => `<option value="${escapeOptionValue(v)}">${v}</option>`).join("");
        }

        function serviceOptionsForSelectedType() {
            const type = customerServiceType ? customerServiceType.value : "Document Printing";
            return servicePrices.filter(item => item.service_type === type);
        }

        function servicePriceSize(item) {
            if (!item) return "";
            const detailedValue = String(item.option_size || "").trim();
            const labelValue = String(item.option_label || "").trim();
            return detailedValue || labelValue || "Standard";
        }

        function servicePriceMaterial(item) {
            if (!item) return "";
            if (isDetailedServiceOrder()) {
                return String(item.option_label || "").trim() || "Standard";
            }
            return "Standard";
        }

        function servicePricePrintType(item) {
            if (!item) return "";
            if (isDetailedServiceOrder()) {
                return String(item.unit || "").trim() || "Standard";
            }
            return "Standard";
        }

        function uniqueServiceValues(mapper, filter = {}) {
            const values = [];
            serviceOptionsForSelectedType().forEach(item => {
                const matches = Object.keys(filter).every(key => {
                    if (key === "size") return servicePriceSize(item) === filter[key];
                    if (key === "material") return servicePriceMaterial(item) === filter[key];
                    if (key === "printType") return servicePricePrintType(item) === filter[key];
                    return true;
                });
                const value = mapper(item);
                if (matches && value !== "" && !values.includes(value)) {
                    values.push(value);
                }
            });
            return values;
        }

        function selectedService() {
            return services.find(s =>
                s.paper_size === paperSize.value &&
                s.paper_type === paperType.value &&
                s.print_type === printType.value
            );
        }

        function selectedServicePrice() {
            if (!serviceSize || !serviceMaterial || !servicePrintType) return null;
            return serviceOptionsForSelectedType().find(item =>
                servicePriceSize(item) === serviceSize.value &&
                servicePriceMaterial(item) === serviceMaterial.value &&
                servicePricePrintType(item) === servicePrintType.value
            ) || null;
        }

        function formatMoney(value) {
            const amount = Number.parseFloat(value);
            return Number.isFinite(amount) ? amount.toFixed(2) : "0.00";
        }

        function serviceUnitLabel() {
            return (isTarpaulinPrintingOrder() || isIdPrintingOrder() || isInvitationCardPrintingOrder()) ? "piece" : "item";
        }

        function updatePaperType() {
            fillSelect(paperType, uniqueValues("paper_type", { paper_size: paperSize.value }));
            updatePrintType();
        }

        function updateAll() {
            fillSelect(paperSize, uniqueValues("paper_size"));
            updatePaperType();
        }

        function updatePrintType() {
            fillSelect(printType, uniqueValues("print_type", {
                paper_size: paperSize.value,
                paper_type: paperType.value
            }));
            computeTotal();
        }

        function updateServicePrintTypes() {
            if (!servicePrintType) return;
            fillSelect(servicePrintType, uniqueServiceValues(servicePricePrintType, {
                size: serviceSize ? serviceSize.value : "",
                material: serviceMaterial ? serviceMaterial.value : ""
            }));
            computeTotal();
        }

        function updateServiceMaterials() {
            if (!serviceMaterial) return;
            fillSelect(serviceMaterial, uniqueServiceValues(servicePriceMaterial, {
                size: serviceSize ? serviceSize.value : ""
            }));
            updateServicePrintTypes();
        }

        function updateServiceOptions() {
            if (!serviceSize) return;
            fillSelect(serviceSize, uniqueServiceValues(servicePriceSize));
            updateServiceMaterials();
        }

        function updateOtherServiceLabels() {
            if (!serviceMaterialLabel) return;
            serviceMaterialLabel.textContent = (isPhotoPrintingOrder() || isInvitationCardPrintingOrder()) ? "Paper Type" : "Material";
        }

        function updateOtherServiceSettings() {
            updateOtherServiceLabels();
            updateServiceOptions();
            computeTotal();
        }

        function syncServiceMode() {
            const documentMode = isDocumentOrder();
            const visitOnlyMode = isShopVisitOnlyOrder();
            const availability = selectedAvailability();
            if (orderServiceType) orderServiceType.value = customerServiceType ? customerServiceType.value : "Document Printing";

            if (documentUploadField) documentUploadField.hidden = !documentMode || visitOnlyMode;
            if (otherUploadField) otherUploadField.hidden = documentMode || visitOnlyMode;
            if (serviceVisitNotice) serviceVisitNotice.hidden = !visitOnlyMode;
            if (serviceVisitNote) serviceVisitNote.textContent = availability.note || "Please visit the shop to proceed.";
            if (documentSettings) documentSettings.hidden = !documentMode || visitOnlyMode;
            if (otherSettings) otherSettings.hidden = documentMode || visitOnlyMode;
            if (visitRequiredSettings) visitRequiredSettings.hidden = !visitOnlyMode;
            if (visitRequiredCopy) visitRequiredCopy.textContent = availability.note || "Please visit the shop to proceed.";
            if (settingsCopy) {
                settingsCopy.textContent = visitOnlyMode
                    ? "This service is advertised by the shop but cannot be submitted as an online request."
                    : (documentMode
                    ? "Select the document printing options available from this shop."
                    : (isPhotoPrintingOrder()
                        ? "Choose the photo size, paper type, print type, and quantity for this request."
                        : (isTarpaulinPrintingOrder()
                            ? "Choose the tarpaulin size, material, print type, and quantity for this request."
                            : (isIdPrintingOrder()
                                ? "Choose the ID size, material, print type, and quantity for this request."
                                : (isInvitationCardPrintingOrder()
                                    ? "Choose the card size, paper type, print type, and quantity for this request."
                                    : "Choose the lamination size, material, print type, and quantity for this request.")))));
            }
            if (serviceBasisField) serviceBasisField.hidden = true;

            if (documentFile) documentFile.required = documentMode && !visitOnlyMode;
            if (serviceFile) serviceFile.required = !documentMode && !visitOnlyMode;
            if (serviceQuantity) serviceQuantity.required = !documentMode && !visitOnlyMode;

            if (visitOnlyMode) {
                serviceId.value = "";
                servicePricingId.value = "";
                setPageCount(1, "This service requires a shop visit.");
                computeTotal();
            } else if (documentMode) {
                servicePricingId.value = "";
                updateAll();
                detectDocumentPages();
            } else {
                serviceId.value = services[0] ? services[0].service_id : "";
                setPageCount(1, "Page count is not needed for this service.");
                updateOtherServiceSettings();
            }
        }

        function computeTotal() {
            const documentMode = isDocumentOrder();
            if (isShopVisitOnlyOrder()) {
                serviceId.value = "";
                servicePricingId.value = "";
                total.textContent = "0.00";
                if (paperPrice) paperPrice.textContent = "Price: In-store only";
                if (totalBreakdown) totalBreakdown.textContent = "Please contact or visit the shop to proceed.";
                updateReview();
                return;
            }
            const selected = documentMode ? selectedService() : selectedServicePrice();
            const pages = Math.max(1, parseInt(detectedPageCount.value || "1", 10) || 1);
            const copyCount = Math.max(1, parseInt(copies.value || "1", 10) || 1);
            const quantity = Math.max(1, parseInt(serviceQuantity ? serviceQuantity.value || "1" : "1", 10) || 1);
            const unitPrice = selected ? parseFloat(documentMode ? selected.price_per_page : selected.price) || 0 : 0;
            if (selected && documentMode) {
                serviceId.value = selected.service_id;
                servicePricingId.value = "";
                total.textContent = formatMoney(unitPrice * pages * copyCount);
            } else if (selected) {
                servicePricingId.value = selected.id;
                total.textContent = formatMoney(unitPrice * quantity);
            } else {
                if (documentMode) serviceId.value = "";
                servicePricingId.value = "";
                total.textContent = "0.00";
            }
            if (!documentMode && services[0]) {
                serviceId.value = services[0].service_id;
            }
            if (paperPrice) {
                const unitLabel = serviceUnitLabel();
                paperPrice.textContent = documentMode
                    ? "Price: \u20b1" + formatMoney(unitPrice) + "/page"
                    : "Price: \u20b1" + formatMoney(unitPrice) + " per " + unitLabel;
            }
            if (totalBreakdown) {
                const unitLabel = serviceUnitLabel();
                totalBreakdown.textContent = documentMode
                    ? "Computation: \u20b1" + formatMoney(unitPrice) + " x " + pages + " page" + (pages === 1 ? "" : "s") + " x " + copyCount + " cop" + (copyCount === 1 ? "y" : "ies") + " = \u20b1" + total.textContent
                    : "Computation: \u20b1" + formatMoney(unitPrice) + " x " + quantity + " " + unitLabel + (quantity === 1 ? "" : "s") + " = \u20b1" + total.textContent;
            }
            if (serviceBasis) {
                serviceBasis.textContent = documentMode ? "per page" : "per " + serviceUnitLabel();
            }
            updateReview();
        }

        function showAlert(message) {
            alertBox.textContent = message;
            alertBox.hidden = false;
        }

        function clearAlert() {
            alertBox.hidden = true;
            alertBox.textContent = "";
        }

        function validateStep(step) {
            clearAlert();

            if (step === 0) {
                if (isShopVisitOnlyOrder()) {
                    return true;
                }
                const file = isDocumentOrder()
                    ? (documentFile.files && documentFile.files[0] ? documentFile.files[0] : null)
                    : (serviceFile.files && serviceFile.files[0] ? serviceFile.files[0] : null);
                const fileValidationMessage = isDocumentOrder() ? validateDocumentFile(file) : validateServiceFile(file);
                if (fileValidationMessage) {
                    showAlert(fileValidationMessage);
                    (isDocumentOrder() ? documentFile : serviceFile).focus();
                    return false;
                }
                if (isDocumentOrder() && pageCountParseFailed) {
                    showAlert("Please upload a valid PDF file. The file you selected cannot be read as a PDF.");
                    documentFile.focus();
                    return false;
                }
                if (!isDocumentOrder() && serviceFileVerifying) {
                    showAlert("Please wait while the file is being checked.");
                    serviceFile.focus();
                    return false;
                }
                if (!isDocumentOrder() && serviceFileParseFailed) {
                    showAlert("Please upload a valid file. The file you selected cannot be read as a valid PDF or image.");
                    serviceFile.focus();
                    return false;
                }
            }

            if (step === 1) {
                if (isShopVisitOnlyOrder()) {
                    showAlert("This service requires a shop visit. Please contact or visit the shop to proceed.");
                    return false;
                }
                if (isDocumentOrder() && pageCountLoading) {
                    showAlert("Please wait while the PDF pages are being counted.");
                    return false;
                }
                if (isDocumentOrder() && pageCountParseFailed) {
                    showAlert("Please upload a valid PDF file. The file you selected cannot be read as a PDF.");
                    return false;
                }
                if (!isDocumentOrder() && serviceFileVerifying) {
                    showAlert("Please wait while the file is being checked.");
                    return false;
                }
                if (!isDocumentOrder() && serviceFileParseFailed) {
                    showAlert("Please upload a valid file. The file you selected cannot be read as a valid PDF or image.");
                    return false;
                }
                if (isDocumentOrder() && (!serviceId.value || !selectedService())) {
                    showAlert("Please choose valid document print settings.");
                    paperSize.focus();
                    return false;
                }
                if (!isDocumentOrder() && (!servicePricingId.value || !selectedServicePrice())) {
                    showAlert("Please choose valid service settings.");
                    (serviceSize || serviceMaterial || servicePrintType || serviceQuantity).focus();
                    return false;
                }
                if (isDocumentOrder() && parseInt(copies.value || "0") < 1) {
                    showAlert("Copies must be at least 1.");
                    copies.focus();
                    return false;
                }
                if (!isDocumentOrder() && parseInt(serviceQuantity.value || "0") < 1) {
                    showAlert("Quantity must be at least 1.");
                    serviceQuantity.focus();
                    return false;
                }
            }

            if (step === 2) {
                const selectedPickup = pickupDatetime.value ? new Date(pickupDatetime.value) : null;
                const now = new Date();
                if (!selectedPickup || Number.isNaN(selectedPickup.getTime()) || selectedPickup < now) {
                    showAlert("Please select a valid future pickup date and time.");
                    pickupDatetime.focus();
                    return false;
                }
            }

            return true;
        }

        function updateReview() {
            const documentMode = isDocumentOrder();
            const activeFile = documentMode ? documentFile : serviceFile;
            const fileName = activeFile.files && activeFile.files[0] ? activeFile.files[0].name : "Not selected";
            const pages = Math.max(1, parseInt(detectedPageCount.value || "1", 10) || 1);
            const copyCount = Math.max(1, parseInt(copies.value || "1", 10) || 1);
            const quantity = Math.max(1, parseInt(serviceQuantity ? serviceQuantity.value || "1" : "1", 10) || 1);
            const selected = documentMode ? selectedService() : selectedServicePrice();
            const unitPrice = selected ? parseFloat(documentMode ? selected.price_per_page : selected.price) || 0 : 0;
            const pickupText = pickupDatetime.value ? new Date(pickupDatetime.value).toLocaleString([], {
                year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit"
            }) : "-";
            const paperTypeRow = document.querySelector("[data-review-paper-type-row]");
            const printTypeRow = document.querySelector("[data-review-print-type-row]");
            const pricingBasisRow = document.querySelector("[data-review-pricing-basis-row]");
            const photoMode = isPhotoPrintingOrder();
            const tarpaulinMode = isTarpaulinPrintingOrder();
            const idMode = isIdPrintingOrder();
            const invitationCardMode = isInvitationCardPrintingOrder();
            if (paperTypeRow) paperTypeRow.hidden = false;
            if (printTypeRow) printTypeRow.hidden = false;
            if (pricingBasisRow) pricingBasisRow.hidden = !documentMode;

            document.querySelector("[data-review-paper-size-label]").textContent = documentMode ? "Paper Size" : "Size";
            document.querySelector("[data-review-paper-type-label]").textContent = documentMode ? "Paper Type" : "Paper Type";
            document.querySelector("[data-review-print-type-label]").textContent = documentMode ? "Print Type" : "Print Type";
            if (photoMode || invitationCardMode) document.querySelector("[data-review-paper-type-label]").textContent = "Paper Type";
            if (tarpaulinMode || idMode || (!documentMode && !photoMode && !invitationCardMode)) document.querySelector("[data-review-paper-type-label]").textContent = "Material";
            document.querySelector("[data-review-pages-label]").textContent = documentMode ? "Pages" : "Quantity";

            document.querySelector("[data-review-service]").textContent = customerServiceType ? customerServiceType.value : "Document Printing";
            document.querySelector("[data-review-file]").textContent = fileName;
            document.querySelector("[data-review-paper-size]").textContent = documentMode ? (paperSize.value || "-") : (selected ? servicePriceSize(selected) : "-");
            document.querySelector("[data-review-paper-type]").textContent = documentMode ? (paperType.value || "-") : (selected ? servicePriceMaterial(selected) : "-");
            document.querySelector("[data-review-print-type]").textContent = documentMode ? (printType.value || "-") : (selected ? servicePricePrintType(selected) : "-");
            document.querySelector("[data-review-pricing-basis]").textContent = "Per Page";
            document.querySelector("[data-review-paper-price]").textContent = documentMode ? ("\u20b1" + formatMoney(unitPrice) + "/page") : ("\u20b1" + formatMoney(unitPrice) + " per " + serviceUnitLabel());
            document.querySelector("[data-review-pages]").textContent = documentMode ? String(pages) : String(quantity);
            document.querySelector("[data-review-copies]").textContent = documentMode ? String(copyCount) : "N/A";
            document.querySelector("[data-review-pickup]").textContent = pickupText;
            document.querySelector("[data-review-instruction]").textContent = instruction.value.trim() || "None";
            document.querySelector("[data-review-total]").textContent = total.textContent;
            if (reviewBreakdown) {
                const unitLabel = serviceUnitLabel();
                reviewBreakdown.textContent = documentMode
                    ? "Computation: \u20b1" + formatMoney(unitPrice) + " x " + pages + " page" + (pages === 1 ? "" : "s") + " x " + copyCount + " cop" + (copyCount === 1 ? "y" : "ies") + " = \u20b1" + total.textContent
                    : "Computation: \u20b1" + formatMoney(unitPrice) + " x " + quantity + " " + unitLabel + (quantity === 1 ? "" : "s") + " = \u20b1" + total.textContent;
            }
        }

        function goToStep(step) {
            currentStep = Math.max(0, Math.min(step, panels.length - 1));
            clearAlert();

            panels.forEach((panel, index) => {
                const active = index === currentStep;
                panel.hidden = !active;
                panel.classList.toggle("is-active", active);
            });

            indicators.forEach((item, index) => {
                item.classList.toggle("is-active", index === currentStep);
                item.classList.toggle("is-complete", index < currentStep);
            });

            backButton.hidden = currentStep === 0;
            cancelLink.hidden = currentStep !== 0;
            nextButton.hidden = currentStep === panels.length - 1;
            submitButton.hidden = currentStep !== panels.length - 1;

            if (currentStep === panels.length - 1) {
                updateReview();
            }
        }

        paperSize.onchange = updatePaperType;
        paperType.onchange = updatePrintType;
        printType.onchange = computeTotal;
        copies.oninput = computeTotal;
        if (customerServiceType) customerServiceType.onchange = syncServiceMode;
        if (serviceSize) serviceSize.onchange = updateServiceMaterials;
        if (serviceMaterial) serviceMaterial.onchange = updateServicePrintTypes;
        if (servicePrintType) servicePrintType.onchange = computeTotal;
        if (serviceQuantity) serviceQuantity.oninput = computeTotal;
        documentFile.onchange = detectDocumentPages;
        if (serviceFile) serviceFile.onchange = function () {
            detectServiceFile();
            updateReview();
        };
        pickupDatetime.onchange = updateReview;
        instruction.oninput = updateReview;

        backButton.addEventListener("click", () => goToStep(currentStep - 1));
        nextButton.addEventListener("click", () => {
            if (validateStep(currentStep)) {
                goToStep(currentStep + 1);
            }
        });
        function buildOrderDraftPayload() {
            const fileInput = isDocumentOrder() ? documentFile : serviceFile;
            const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            const shopIdInput = wizard.querySelector('[name="shop_id"]');
            return {
                shop_id: shopIdInput ? shopIdInput.value : '',
                shop_name: <?php echo json_encode($shop['shop_name']); ?>,
                order_service_type: customerServiceType ? customerServiceType.value : 'Document Printing',
                service_id: serviceId ? serviceId.value : '',
                service_pricing_id: servicePricingId ? servicePricingId.value : '',
                detected_page_count: detectedPageCount ? detectedPageCount.value : '1',
                copies: copies ? copies.value : '1',
                service_quantity: serviceQuantity ? serviceQuantity.value : '1',
                pickup_datetime: pickupDatetime ? pickupDatetime.value : '',
                customer_instruction: instruction ? instruction.value.trim() : '',
                file_key: isDocumentOrder() ? 'document_file' : 'service_file',
                file: file ? { name: file.name, type: file.type, size: file.size, blob: file } : null
            };
        }

        wizard.addEventListener("submit", function (event) {
            if (formSubmitting) {
                event.preventDefault();
                return;
            }
            for (let step = 0; step < panels.length - 1; step++) {
                if (!validateStep(step)) {
                    event.preventDefault();
                    goToStep(step);
                    return;
                }
            }

            if (navigator.onLine === false) {
                event.preventDefault();
                if (window.OrderDrafts) {
                    window.OrderDrafts.saveDraft(buildOrderDraftPayload()).then(function () {
                        if (window.customerShowToast) {
                            window.customerShowToast("Your request was saved. It will be sent automatically when you're back online.", "success", { title: "Saved as draft" });
                        }
                        wizard.reset();
                        syncServiceMode();
                        goToStep(0);
                    }).catch(function () {
                        if (window.customerShowToast) {
                            window.customerShowToast("Could not save your request offline. Please try again when you're back online.", "error", { title: "Save failed" });
                        }
                    });
                } else {
                    if (window.customerShowToast) {
                        window.customerShowToast("Offline saving is not supported on this browser. Please try again when you're back online.", "error", { title: "Unavailable" });
                    }
                }
                return;
            }

            formSubmitting = true;
            submitButton.textContent = "Submitting...";
        });

        syncServiceMode();
        goToStep(0);
    </script>

    <?php renderCustomerLayoutEnd('explore'); ?>

</body>

</html>
