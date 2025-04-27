let cart = []; // Khởi tạo giỏ hàng rỗng

// Hàm thêm sản phẩm vào giỏ hàng
function addToCart(name, price, imageUrl) {
  let productIndex = cart.findIndex((item) => item.name === name);

  // Nếu sản phẩm đã có trong giỏ hàng, tăng số lượng
  if (productIndex > -1) {
    cart[productIndex].quantity += 1;
  } else {
    // Nếu chưa có, thêm sản phẩm mới vào giỏ hàng
    cart.push({ name, price, quantity: 1, imageUrl });
  }

  // Cập nhật lại giỏ hàng
  displayCart();
}

// Hàm hiển thị giỏ hàng
function displayCart() {
  let cartItems = document.getElementById("cart-items");
  let totalPrice = 0;
  cartItems.innerHTML = ""; // Xóa các mục cũ trong giỏ hàng

  cart.forEach((item, index) => {
    const li = document.createElement("li");

    // Tạo thẻ <img> để hiển thị hình ảnh sản phẩm
    const img = document.createElement("img");
    img.src = `./img/${item.imageUrl}`; // Sử dụng đường dẫn chính xác đến ảnh
    img.alt = item.name;
    img.style.width = "50px"; // Kích thước hình ảnh cho phù hợp
    img.style.height = "auto";

    // Tạo thẻ input cho số lượng sản phẩm
    const quantityInput = document.createElement("input");
    quantityInput.type = "number";
    quantityInput.value = item.quantity;
    quantityInput.min = "1"; // Đảm bảo không nhập số lượng nhỏ hơn 1
    quantityInput.classList.add("product-quantity");
    quantityInput.addEventListener("change", function () {
      updateQuantity(index, quantityInput.value);
    });

    // Tạo nội dung văn bản cho sản phẩm
    const textContent = `${item.name} - ${
      item.quantity
    } x ${item.price.toLocaleString()} VNĐ`;

    // Tạo nút xóa sản phẩm
    const removeButton = document.createElement("button");
    removeButton.classList.add("remove-item");
    removeButton.innerHTML = '<i class="fas fa-trash-alt"></i> Xóa';
    removeButton.onclick = function () {
      removeFromCart(index); // Gọi hàm xóa sản phẩm theo chỉ số
    };

    // Thêm hình ảnh, thông tin sản phẩm và nút xóa vào danh sách
    li.appendChild(img);
    li.appendChild(document.createTextNode(textContent));
    li.appendChild(quantityInput);
    li.appendChild(removeButton);
    cartItems.appendChild(li);

    totalPrice += item.price * item.quantity;
  });

  // Hiển thị tổng giá trị của giỏ hàng
  document.getElementById(
    "total-price"
  ).textContent = `Tổng: ${totalPrice.toLocaleString()} VNĐ`;
}

// Hàm xóa sản phẩm khỏi giỏ hàng
function removeFromCart(index) {
  cart.splice(index, 1); // Xóa sản phẩm khỏi giỏ hàng theo chỉ số
  displayCart(); // Cập nhật lại giỏ hàng
}

// Hàm cập nhật số lượng sản phẩm trong giỏ hàng
function updateQuantity(index, quantity) {
  if (quantity < 1) {
    alert("Số lượng phải lớn hơn 0!");
    return; // Nếu số lượng nhập vào nhỏ hơn 1, không thực hiện thay đổi
  }

  cart[index].quantity = parseInt(quantity);
  displayCart(); // Cập nhật lại giỏ hàng
}

// Hàm hiển thị modal
function showModal() {
  document.getElementById("success-modal").style.display = "block";
}

// Hàm đóng modal
function closeModal() {
  document.getElementById("success-modal").style.display = "none";
}

// Hàm xử lý thanh toán
function submitOrder(event) {
  event.preventDefault();

  // Lấy thông tin từ form khách hàng
  const name = document.getElementById("customer-name").value;
  const email = document.getElementById("customer-email").value;
  const phone = document.getElementById("customer-phone").value;
  const address = document.getElementById("customer-address").value;
  const request = document.getElementById("customer-request").value; // Lấy thông tin yêu cầu khác

  // Lấy phương thức thanh toán đã chọn
  const paymentMethod = document.querySelector(
    'input[name="payment-method"]:checked'
  ); // Lấy phương thức thanh toán

  if (paymentMethod) {
    const payment = paymentMethod.value;
    console.log("Phương thức thanh toán:", payment);
  } else {
    alert("Vui lòng chọn phương thức thanh toán!");
    return; // Nếu không chọn phương thức thanh toán, dừng thực hiện
  }

  if (name && email && phone && address && cart.length > 0) {
    const order = {
      customer: {
        name,
        email,
        phone,
        address,
        request,
        paymentMethod: paymentMethod.value,
      }, // Thêm yêu cầu và phương thức thanh toán vào đơn hàng
      items: cart,
      totalPrice: cart.reduce(
        (total, item) => total + item.price * item.quantity,
        0
      ),
    };

    console.log("Thông tin đơn hàng:", order);

    // Hiển thị thông báo thành công dưới dạng modal thay vì alert
    showModal();

    // Đặt lại giỏ hàng và form
    cart = [];
    displayCart();
    document.getElementById("customer-form").reset();
  } else {
    alert("Vui lòng điền đầy đủ thông tin và giỏ hàng không rỗng!");
  }
}

// Đóng modal khi nhấp vào nút đóng (X)
document.addEventListener("DOMContentLoaded", function () {
  const closeButton = document.querySelector(".close-button");
  if (closeButton) {
    closeButton.onclick = closeModal;
  }

  // Đóng modal khi nhấp bên ngoài modal
  window.onclick = function (event) {
    const modal = document.getElementById("success-modal");
    if (event.target === modal) {
      closeModal();
    }
  };
});
