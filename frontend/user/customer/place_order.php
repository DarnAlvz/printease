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
    setToast("This print shop is currently not accepting orders.", "warning");
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

date_default_timezone_set('Asia/Manila');
$min_pickup = date('Y-m-d\TH:i');
$shop_logo_file = trim((string) ($shop['shop_logo'] ?? ''));
$shop_logo_url = $shop_logo_file !== '' ? SHOP_LOGOS_URL . basename($shop_logo_file) : '';
$shop_is_busy = ($shop['shop_status'] ?? '') === 'busy';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Place Order</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-5xl mx-auto min-h-screen">

        <?php renderCustomerLayout(['title' => 'Place Order', 'subtitle' => 'Ordering from ' . $shop['shop_name']]); ?>

        <main class="p-4 md:p-6">
            <form action="../../../backend/actions/submit_order.php" method="POST" enctype="multipart/form-data"
                class="customer-order-wizard bg-white p-5 md:p-6 rounded-2xl shadow" data-order-wizard novalidate>
                <input type="hidden" name="shop_id" value="<?php echo e($shop['shop_id']); ?>">
                <input type="hidden" name="service_id" id="service_id">
                <input type="hidden" name="detected_page_count" id="detected_page_count" value="1">

                <ol class="customer-order-steps" aria-label="Order progress">
                    <li class="is-active" data-step-indicator="0"><span>1</span><strong>File</strong></li>
                    <li data-step-indicator="1"><span>2</span><strong>Settings</strong></li>
                    <li data-step-indicator="2"><span>3</span><strong>Pickup</strong></li>
                    <li data-step-indicator="3"><span>4</span><strong>Review</strong></li>
                </ol>

                <p class="customer-order-wizard-alert" data-wizard-alert role="alert" hidden></p>

                <?php if ($shop_is_busy): ?>
                    <section class="customer-busy-shop-inline" role="status">
                        <strong>Notice: This shop is currently busy.</strong>
                        <span>This shop is in high demand and has many orders at the moment, so your request may take longer than usual.</span>
                    </section>
                <?php endif; ?>

                <section class="customer-order-step is-active" data-step-panel="0" aria-labelledby="orderStepFile">
                    <div class="customer-order-step-head">
                        <span>Step 1 of 4</span>
                        <h2 id="orderStepFile">Upload your document</h2>
                        <p>Choose the file you want this shop to print.</p>
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
                        <span>Upload Document</span>
                        <input type="file" name="document_file" id="document_file" required
                            class="w-full border rounded-xl p-3">
                        <small>Each order must contain exactly one file attachment only.
                            Only PDF files are accepted.
                        </small>
                    </label>
                </section>

                <section class="customer-order-step" data-step-panel="1" aria-labelledby="orderStepSettings" hidden>
                    <div class="customer-order-step-head">
                        <span>Step 2 of 4</span>
                        <h2 id="orderStepSettings">Choose print settings</h2>
                        <p>Select the service options available from this shop.</p>
                    </div>

                    <div class="customer-order-grid">
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

                    <div class="customer-order-total">
                        <span>Estimated Total</span>
                        <strong>&#8369;<span id="total">0.00</span></strong>
                        <small class="block text-sm text-gray-700 mt-1" data-paper-price>Paper Price: &#8369;0.00/page</small>
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
                        <h2 id="orderStepReview">Review order</h2>
                        <p>Check the details before submitting your print order.</p>
                    </div>

                    <div class="customer-order-review">
                        <div><span>Shop</span><strong><?php echo e($shop['shop_name']); ?></strong></div>
                        <div><span>Document</span><strong data-review-file>Not selected</strong></div>
                        <div><span>Paper Size</span><strong data-review-paper-size>-</strong></div>
                        <div><span>Paper Type</span><strong data-review-paper-type>-</strong></div>
                        <div><span>Print Type</span><strong data-review-print-type>-</strong></div>
                        <div><span>Paper Price</span><strong data-review-paper-price>&#8369;0.00/page</strong></div>
                        <div><span>Pages</span><strong data-review-pages>1</strong></div>
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
                        Submit Order
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        const services = <?php echo json_encode($service_list); ?>;

        const wizard = document.querySelector("[data-order-wizard]");
        const indicators = Array.from(document.querySelectorAll("[data-step-indicator]"));
        const panels = Array.from(document.querySelectorAll("[data-step-panel]"));
        const alertBox = document.querySelector("[data-wizard-alert]");
        const backButton = document.querySelector("[data-wizard-back]");
        const nextButton = document.querySelector("[data-wizard-next]");
        const submitButton = document.querySelector("[data-wizard-submit]");
        const cancelLink = document.querySelector("[data-wizard-cancel]");
        const documentFile = document.getElementById("document_file");
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
        let currentStep = 0;
        let pageCountLoading = false;
        let pageCountRequestId = 0;

        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
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

        async function detectDocumentPages() {
            const file = documentFile.files && documentFile.files[0] ? documentFile.files[0] : null;
            const requestId = ++pageCountRequestId;

            if (!file) {
                pageCountLoading = false;
                setPageCount(1, "Upload a PDF to detect pages automatically.");
                return;
            }

            if (!selectedFileIsPdf(file)) {
                pageCountLoading = false;
                setPageCount(1, "Non-PDF files are counted as 1 page.");
                return;
            }

            if (!window.pdfjsLib) {
                pageCountLoading = false;
                setPageCount(1, "PDF page counter is unavailable. 1 page will be used.");
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
                setPageCount(1, "Could not read PDF pages. 1 page will be used.");
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

        function selectedService() {
            return services.find(s =>
                s.paper_size === paperSize.value &&
                s.paper_type === paperType.value &&
                s.print_type === printType.value
            );
        }

        function formatMoney(value) {
            const amount = Number.parseFloat(value);
            return Number.isFinite(amount) ? amount.toFixed(2) : "0.00";
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

        function computeTotal() {
            const selected = selectedService();
            const pages = Math.max(1, parseInt(detectedPageCount.value || "1", 10) || 1);
            const copyCount = Math.max(1, parseInt(copies.value || "1", 10) || 1);
            const unitPrice = selected ? parseFloat(selected.price_per_page) || 0 : 0;
            if (selected) {
                serviceId.value = selected.service_id;
                total.textContent = formatMoney(unitPrice * pages * copyCount);
            } else {
                serviceId.value = "";
                total.textContent = "0.00";
            }
            if (paperPrice) {
                paperPrice.textContent = "Paper Price: \u20b1" + formatMoney(unitPrice) + "/page";
            }
            if (totalBreakdown) {
                totalBreakdown.textContent = "Computation: \u20b1" + formatMoney(unitPrice) + " x " + pages + " page" + (pages === 1 ? "" : "s") + " x " + copyCount + " cop" + (copyCount === 1 ? "y" : "ies") + " = \u20b1" + total.textContent;
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

            if (step === 0 && (!documentFile.files || documentFile.files.length === 0)) {
                showAlert("Please upload your document before continuing.");
                documentFile.focus();
                return false;
            }

            if (step === 1) {
                if (pageCountLoading) {
                    showAlert("Please wait while the PDF pages are being counted.");
                    return false;
                }
                if (!serviceId.value || !selectedService()) {
                    showAlert("Please choose valid print settings.");
                    paperSize.focus();
                    return false;
                }
                if (parseInt(copies.value || "0") < 1) {
                    showAlert("Copies must be at least 1.");
                    copies.focus();
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
            const fileName = documentFile.files && documentFile.files[0] ? documentFile.files[0].name : "Not selected";
            const pages = Math.max(1, parseInt(detectedPageCount.value || "1", 10) || 1);
            const copyCount = Math.max(1, parseInt(copies.value || "1", 10) || 1);
            const selected = selectedService();
            const unitPrice = selected ? parseFloat(selected.price_per_page) || 0 : 0;
            const pickupText = pickupDatetime.value ? new Date(pickupDatetime.value).toLocaleString([], {
                year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit"
            }) : "-";

            document.querySelector("[data-review-file]").textContent = fileName;
            document.querySelector("[data-review-paper-size]").textContent = paperSize.value || "-";
            document.querySelector("[data-review-paper-type]").textContent = paperType.value || "-";
            document.querySelector("[data-review-print-type]").textContent = printType.value || "-";
            document.querySelector("[data-review-paper-price]").textContent = "\u20b1" + formatMoney(unitPrice) + "/page";
            document.querySelector("[data-review-pages]").textContent = String(pages);
            document.querySelector("[data-review-copies]").textContent = String(copyCount);
            document.querySelector("[data-review-pickup]").textContent = pickupText;
            document.querySelector("[data-review-instruction]").textContent = instruction.value.trim() || "None";
            document.querySelector("[data-review-total]").textContent = total.textContent;
            if (reviewBreakdown) {
                reviewBreakdown.textContent = "Computation: \u20b1" + formatMoney(unitPrice) + " x " + pages + " page" + (pages === 1 ? "" : "s") + " x " + copyCount + " cop" + (copyCount === 1 ? "y" : "ies") + " = \u20b1" + total.textContent;
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
        documentFile.onchange = detectDocumentPages;
        pickupDatetime.onchange = updateReview;
        instruction.oninput = updateReview;

        backButton.addEventListener("click", () => goToStep(currentStep - 1));
        nextButton.addEventListener("click", () => {
            if (validateStep(currentStep)) {
                goToStep(currentStep + 1);
            }
        });
        wizard.addEventListener("submit", function (event) {
            for (let step = 0; step < panels.length - 1; step++) {
                if (!validateStep(step)) {
                    event.preventDefault();
                    goToStep(step);
                    return;
                }
            }
        });

        updateAll();
        goToStep(0);
    </script>

    <?php renderCustomerLayoutEnd('explore'); ?>

</body>

</html>
