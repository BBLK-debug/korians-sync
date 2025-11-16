document.addEventListener('DOMContentLoaded', function () {

    /** 🔹 سیستم تب‌ها **/
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');

            const target = this.getAttribute('href');
            contents.forEach(c => c.classList.remove('active'));
            document.querySelector(target).classList.add('active');
        });
    });

    /** 🔹 حذف سایت از لیست در Master **/
    document.querySelectorAll('.remove-site').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            if (confirm('آیا از حذف این سایت اطمینان دارید؟')) {
                row.remove();
            }
        });
    });

    /** 🔹 پاکسازی لاگ‌ها **/
    const clearBtn = document.getElementById('clear-log');
    if (clearBtn) {
        clearBtn.addEventListener('click', async function () {
            if (!confirm('آیا از پاک‌سازی لاگ‌ها مطمئن هستید؟')) return;

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'syncmaster_clear_log',
                    _ajax_nonce: syncmaster_admin.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('✅ لاگ‌ها با موفقیت پاک شدند');
                location.reload();
            } else {
                alert('❌ خطا در پاک‌سازی لاگ‌ها');
            }
        });
    }

    /** 🔹 تست اتصال به سایت فرزند **/
    const testBtn = document.getElementById('test-connection');
    if (testBtn) {
        testBtn.addEventListener('click', async function () {
            const url = document.querySelector('input[name="master_url"]')?.value || '';
            const license = document.querySelector('input[name="license"]')?.value || '';
            if (!url || !license) {
                alert('لطفاً آدرس سایت و لایسنس را وارد کنید.');
                return;
            }

            testBtn.textContent = '⏳ در حال بررسی...';
            try {
                const res = await fetch(`${url}/wp-json/wms/v1/license/verify`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ license })
                });
                const data = await res.json();

                if (data.valid) {
                    alert('✅ ارتباط برقرار و لایسنس معتبر است.');
                } else {
                    alert('❌ لایسنس نامعتبر یا پاسخ اشتباه.');
                }
            } catch (err) {
                alert('⚠️ خطا در ارتباط با سرور: ' + err.message);
            } finally {
                testBtn.textContent = '🔍 تست ارتباط';
            }
        });
    }

});
