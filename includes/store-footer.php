</main>

<!-- STORE FOOTER -->
<footer class="bg-gray-800 dark:bg-gray-900 text-gray-400 mt-12 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-3 gap-8">
        <div>
            <h3 class="text-white font-bold text-lg mb-3">🛒 <?= SHOP_NAME ?></h3>
            <p class="text-sm dark:text-gray-400"><?= SHOP_TAGLINE ?></p>
            <p class="text-sm mt-2 dark:text-gray-400"><?= SHOP_ADDRESS ?></p>
        </div>
        <div>
            <h4 class="text-white font-semibold mb-3"><?= __('quick_links') ?></h4>
            <ul class="space-y-1 text-sm">
                <li><a href="<?= BASE_URL ?>store/" class="hover:text-white transition"><?= __('home') ?></a></li>
                <li><a href="<?= BASE_URL ?>store/cart.php" class="hover:text-white transition"><?= __('cart') ?></a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-semibold mb-3"><?= __('contact') ?></h4>
            <p class="text-sm dark:text-gray-400">📞 <?= SHOP_PHONE ?></p>
            <p class="text-sm dark:text-gray-400">✉️ <?= SHOP_EMAIL ?></p>
            <a href="https://wa.me/<?= WHATSAPP_NO ?>" target="_blank"
               class="mt-3 inline-flex items-center gap-2 bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition">
                <?= __('whatsapp_us') ?>
            </a>
        </div>
    </div>
    <div class="border-t border-gray-700 text-center py-4 text-xs dark:text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?>. <?= __('all_rights_reserved') ?>.
    </div>
</footer>

<script src="<?= BASE_URL ?>assets/js/store.js"></script>
</body>
</html>
