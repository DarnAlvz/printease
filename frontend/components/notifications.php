<?php

require_once __DIR__ . '/branding.php';

function getUserNotifications($conn, $user_id, $limit = 100)
{
    $limit = max(1, min(200, (int) $limit));
    $sql = "SELECT notification_id, user_id, COALESCE(type, 'general') AS type,
                   COALESCE(title, 'Notification') AS title, message, target_url,
                   metadata_json, is_read, read_at, created_at
            FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

function getUnreadNotificationCount($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (int) ($row['total'] ?? 0);
}

function notificationIconName($type)
{
    return match ((string) $type) {
        'order_new', 'order_status', 'pickup_reminder' => 'package',
        'payment_submitted', 'payment_verified', 'payment_rejected', 'payment_settings_submitted', 'payment_settings_status' => 'credit-card',
        'permit_submitted', 'permit_status' => 'file-check',
        'account_submitted', 'account_status' => 'user-check',
        default => 'bell',
    };
}

function notificationTone(array $notification)
{
    $type = strtolower((string) ($notification['type'] ?? ''));
    $title = strtolower((string) ($notification['title'] ?? ''));
    $message = strtolower((string) ($notification['message'] ?? ''));
    $status = '';

    if (!empty($notification['metadata_json'])) {
        $decoded = json_decode((string) $notification['metadata_json'], true);
        if (is_array($decoded)) {
            $status = strtolower((string) ($decoded['status'] ?? ''));
        }
    }

    $text = trim($type . ' ' . $title . ' ' . $message . ' ' . $status);

    if ($type === 'payment_rejected' || str_contains($text, 'rejected')) {
        return 'danger';
    }

    if ($type === 'order_status' && ($status === 'ready_for_pickup' || str_contains($text, 'ready for pickup'))) {
        return 'success';
    }

    if ($type === 'order_status' && ($status === 'processing' || str_contains($text, 'accepted') || str_contains($text, 'processing'))) {
        return 'info';
    }

    return 'default';
}

function notificationSafeTarget($url)
{
    return isInternalAppUrl($url) ? (string) $url : '';
}

function renderNotificationCenter(array $notifications, array $options = [])
{
    $role = (string) ($options['role'] ?? 'customer');
    $unread_count = (int) ($options['unread_count'] ?? 0);
    $empty_text = (string) ($options['empty_text'] ?? 'Updates will appear here.');
    ?>
    <section class="notification-center" data-notification-center>
    <div class="notification-center-head">
        <h2>Recent notifications</h2>
        <button class="notification-mark-all" type="button" data-mark-all-read <?php echo $unread_count === 0 ? 'disabled' : ''; ?>>Mark all read</button>
    </div>
    <?php if (empty($notifications)): ?>
        <div class="notification-empty" role="status">
            <span class="notification-empty-icon"><i data-lucide="bell-off" aria-hidden="true"></i></span>
            <strong>No Notifications</strong>
            <p><?php echo e($empty_text); ?></p>
        </div>
    <?php else: ?>
        <div class="app-notification-list">
            <?php foreach ($notifications as $notification): ?>
                <?php $target = notificationSafeTarget($notification['target_url'] ?? ''); $tag = $target !== '' ? 'a' : 'article'; $tone = notificationTone($notification); ?>
                <<?php echo $tag; ?> class="app-notification notification-tone-<?php echo e($tone); ?> <?php echo empty($notification['is_read']) ? 'is-unread' : ''; ?>"
                    data-role="<?php echo e($role); ?>" data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                    data-is-read="<?php echo (int) $notification['is_read']; ?>"
                    data-notification-type="<?php echo e($notification['type'] ?? 'general'); ?>"
                    data-notification-tone="<?php echo e($tone); ?>" <?php echo $target !== '' ? 'href="' . e($target) . '"' : ''; ?>>
                    <span class="app-notification-icon"><i data-lucide="<?php echo e(notificationIconName($notification['type'])); ?>" aria-hidden="true"></i></span>
                    <span class="app-notification-content">
                        <strong class="app-notification-title"><?php echo e($notification['title'] ?: 'Notification'); ?><?php if (empty($notification['is_read'])): ?><span class="app-notification-new" aria-label="Unread"></span><?php endif; ?></strong>
                        <span class="app-notification-message"><?php echo e($notification['message']); ?></span>
                    </span>
                    <time class="app-notification-time" datetime="<?php echo e($notification['created_at']); ?>"><?php echo e(notificationRelativeTime($notification['created_at'])); ?></time>
                </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>
    <script>
        (function(){
            const endpoint = '<?php echo e(printEaseAssetUrl('backend/actions/mark_notification_read.php')); ?>';
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.content : '';
            function mark(payload, callback){ payload.csrf_token = csrfToken; fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(payload)}).then(r=>r.json()).then(callback).catch(()=>{}); }
            function markReadElement(item){
                item.classList.add('is-marking-read');
                window.setTimeout(function(){
                    item.classList.remove('is-unread','is-marking-read');
                    item.dataset.isRead='1';
                    const dot=item.querySelector('.app-notification-new');
                    if(dot)dot.remove();
                },160);
            }
            document.querySelectorAll('.app-notification[data-notification-id]').forEach(function(item){ item.addEventListener('click',function(event){ if(item.dataset.isRead==='1') return; const href=item.getAttribute('href'); if(href) event.preventDefault(); mark({notification_id:item.dataset.notificationId},function(data){ if(!data.success)return; markReadElement(item); if(href) window.setTimeout(function(){ window.location.href=href; },180); }); }); });
            const all=document.querySelector('[data-mark-all-read]'); if(all) all.addEventListener('click',function(){ mark({mark_all:'1'},function(data){ if(!data.success)return; document.querySelectorAll('.app-notification.is-unread').forEach(markReadElement); all.disabled=true; }); });
            if(window.lucide) window.lucide.createIcons();
        })();
    </script>
    <?php
}
