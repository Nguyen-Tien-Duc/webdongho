document.addEventListener('DOMContentLoaded', async () => {
    // Phần 1: Tải và hiển thị danh sách sản phẩm
    const productList = document.getElementById('product-list');

    if (productList) {
        try {
            const response = await fetch('/backend/server.php?action=getProducts');
            const products = await response.json();

            if (products.length > 0) {
                products.forEach(product => {
                    const productItem = document.createElement('div');
                    productItem.classList.add('product-item');

                    let discountHtml = '';
                    if (product.discount) {
                        discountHtml = `<span class="discount">${product.discount}</span>`;
                    }

                    let oldPriceHtml = '';
                    if (product.old_price) {
                        oldPriceHtml = `<span class="old-price">${Number(product.old_price).toLocaleString()} VNĐ</span>`;
                    }

                    productItem.innerHTML = `
                        ${discountHtml}
                        <img src="../assets/images/${product.image}" alt="${product.name}">
                        <div class="product-info">
                            <h4>${product.name}</h4>
                            <p class="price">${Number(product.price).toLocaleString()} VNĐ ${oldPriceHtml}</p>
                            <button class="add-to-cart" data-id="${product.id}">Thêm vào giỏ hàng</button>
                        </div>
                    `;

                    productList.appendChild(productItem);
                });
            } else {
                productList.innerHTML = '<p>Không có sản phẩm nào để hiển thị.</p>';
            }
        } catch (error) {
            console.error('Lỗi:', error);
            productList.innerHTML = '<p>Có lỗi xảy ra khi tải sản phẩm.</p>';
        }
    }

    // Phần 2: Xử lý đăng nhập/đăng xuất
    const loginBtn = document.getElementById('login-btn');
    const userInfo = document.getElementById('user-info');
    const accountNameElement = document.getElementById('account-name');

    if (loginBtn && userInfo && accountNameElement) {
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const accountName = localStorage.getItem('accountName') || 'Tài khoản';

        if (isLoggedIn) {
            loginBtn.style.display = 'none';
            userInfo.style.display = 'flex';
            accountNameElement.textContent = accountName;
        } else {
            loginBtn.style.display = 'block';
            userInfo.style.display = 'none';
        }

        document.querySelector('.login-btn a')?.addEventListener('click', function (e) {
            e.preventDefault();
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('accountName', 'Nguyễn Văn A');
            window.location.reload();
        });

        document.querySelector('.user-info a')?.addEventListener('click', function (e) {
            e.preventDefault();
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('accountName');
            window.location.reload();
        });
    }

    // Phần 3: Xử lý toggle menu responsive và cố định menu + hiệu ứng cuộn chuột
    const navToggle = document.getElementById('nav-toggle');
    const mainNav = document.querySelector('.main-nav');
    const header = document.querySelector('.header');
    const hasMegamenuItems = document.querySelectorAll('.has-megamenu');

    if (navToggle && mainNav) {
        // Toggle menu trên mobile
        navToggle.addEventListener('click', () => {
            mainNav.classList.toggle('active');
            navToggle.textContent = mainNav.classList.contains('active') ? '✖' : '☰';
        });

        // Xử lý toggle submenu cho các mục có mega-menu trên mobile
        hasMegamenuItems.forEach(item => {
            const link = item.querySelector('a');
            link.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    item.classList.toggle('active');
                }
            });
        });

        // Xử lý cố định menu và ẩn/hiện menu khi cuộn chuột (chỉ trên desktop)
        let lastScrollTop = 0;
        let isMouseOverMenu = false;
        const navHeight = mainNav.offsetHeight;
        const headerHeight = header.offsetHeight;

        // Đánh dấu khi chuột vào vùng menu
        mainNav.addEventListener('mouseenter', () => {
            if (window.innerWidth > 768) {
                isMouseOverMenu = true;
                mainNav.classList.remove('hidden');
            }
        });

        // Đánh dấu khi chuột rời khỏi vùng menu
        mainNav.addEventListener('mouseleave', () => {
            if (window.innerWidth > 768) {
                isMouseOverMenu = false;
                setTimeout(() => {
                    if (!isMouseOverMenu && window.scrollY > 100) {
                        mainNav.classList.add('hidden');
                    }
                }, 500); // Độ trễ 500ms để menu không ẩn ngay lập tức
            }
        });

        // Xử lý khi cuộn chuột
        window.addEventListener('scroll', () => {
            if (window.innerWidth > 768) {
                let currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;

                // Cố định menu khi cuộn qua ngưỡng
                if (currentScrollTop > headerHeight) {
                    mainNav.classList.add('fixed');
                    header.classList.add('has-fixed-nav');
                } else {
                    mainNav.classList.remove('fixed');
                    header.classList.remove('has-fixed-nav');
                }

                // Ẩn/hiện menu khi cuộn
                if (currentScrollTop > lastScrollTop && currentScrollTop > 100) {
                    // Cuộn xuống và không hover vào menu
                    if (!isMouseOverMenu) {
                        mainNav.classList.add('hidden');
                    }
                } else if (currentScrollTop <= 100) {
                    // Cuộn lên đầu trang
                    mainNav.classList.remove('hidden');
                }

                lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop;
            }
        });

        // Hiện menu mặc định khi tải trang trên desktop
        if (window.innerWidth > 768) {
            mainNav.classList.remove('hidden');
        }
    }
});
