// Product Inventory Management JavaScript

// Global variable to store categories
let categories = [];

// Load categories and populate dropdowns
function loadCategories() {
    fetch('get_categories.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                categories = data.data;
                
                // Populate Add Product category dropdown
                const addCategorySelect = document.getElementById('addCategory');
                addCategorySelect.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.category_id;
                    option.textContent = cat.category_name;
                    addCategorySelect.appendChild(option);
                });
                
                // Populate Edit Product category dropdown
                const editCategorySelect = document.getElementById('editCategory');
                editCategorySelect.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.category_id;
                    option.textContent = cat.category_name;
                    editCategorySelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
        });
}

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

// Calculate margin
function calculateMargin(unitCost, sellingPrice) {
    return parseFloat(sellingPrice) - parseFloat(unitCost);
}

// Format margin display
function formatMargin(margin) {
    const formatted = formatCurrency(Math.abs(margin));
    return margin >= 0 ? formatted : '-' + formatted;
}

// Load products table
function loadProducts() {
    const tableBody = document.getElementById('productsTableBody');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    loadingSpinner.style.display = 'block';
    tableBody.innerHTML = '';
    
    fetch('fetch_products.php')
        .then(response => response.json())
        .then(data => {
            loadingSpinner.style.display = 'none';
            
            if (data.success) {
                if (data.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="9" class="text-center">No products found. Add your first product!</td></tr>';
                } else {
                    data.data.forEach(product => {
                        // Use raw numeric values for calculations
                        const unitCost = parseFloat(product.unit_cost);
                        const sellingPrice = parseFloat(product.selling_price);
                        const margin = parseFloat(product.margin);
                        const marginClass = margin >= 0 ? 'margin-positive' : 'margin-negative';
                        const marginSign = margin >= 0 ? '+' : '';
                        
                        const row = `
                            <tr>
                                <td>${product.product_id}</td>
                                <td>${product.category_name}</td>
                                <td>${product.description}</td>
                                <td>${product.unit}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(sellingPrice)}</td>
                                <td class="${marginClass}">${marginSign}${formatCurrency(margin)}</td>
                                <td>${product.initial_quantity}</td>
                                <td>
                                    <button class="btn btn-sm btn-edit me-2" onclick="editProduct(${product.product_id})">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-delete" onclick="deleteProduct(${product.product_id}, '${product.description.replace(/'/g, "\\'")}')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `;
                        tableBody.innerHTML += row;
                    });
                }
            } else {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading products: ' + data.message + '</td></tr>';
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error: ' + error.message + '</td></tr>';
            console.error('Error:', error);
        });
}

// Calculate margin for Add Product form
function calculateAddMargin() {
    const unitCost = document.getElementById('addUnitCost').value || 0;
    const sellingPrice = document.getElementById('addSellingPrice').value || 0;
    const margin = calculateMargin(unitCost, sellingPrice);
    const marginDisplay = document.getElementById('addMarginDisplay');
    
    marginDisplay.textContent = formatMargin(margin);
    if (margin >= 0) {
        marginDisplay.className = 'margin-display margin-positive';
    } else {
        marginDisplay.className = 'margin-display margin-negative';
    }
}

// Calculate margin for Edit Product form
function calculateEditMargin() {
    const unitCost = document.getElementById('editUnitCost').value || 0;
    const sellingPrice = document.getElementById('editSellingPrice').value || 0;
    const margin = calculateMargin(unitCost, sellingPrice);
    const marginDisplay = document.getElementById('editMarginDisplay');
    
    marginDisplay.textContent = formatMargin(margin);
    if (margin >= 0) {
        marginDisplay.className = 'margin-display margin-positive';
    } else {
        marginDisplay.className = 'margin-display margin-negative';
    }
}

// Add Product
function addProduct() {
    const form = document.getElementById('addProductForm');
    const formData = new FormData(form);
    
    fetch('add_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });
            
            // Close modal and reset form
            const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
            modal.hide();
            form.reset();
            document.getElementById('addMarginDisplay').textContent = '₱0.00';
            document.getElementById('addMarginDisplay').className = 'margin-display';
            
            // Reload products table
            loadProducts();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred: ' + error.message
        });
        console.error('Error:', error);
    });
}

// Edit Product - Load product data
function editProduct(productId) {
    fetch(`get_product.php?product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const product = data.data;
                
                document.getElementById('editProductId').value = product.product_id;
                document.getElementById('editCategory').value = product.category_id || '';
                document.getElementById('editDescription').value = product.description;
                document.getElementById('editUnit').value = product.unit;
                document.getElementById('editCode').value = product.code;
                document.getElementById('editUnitCost').value = product.unit_cost;
                document.getElementById('editSellingPrice').value = product.selling_price;
                document.getElementById('editInitialQuantity').value = product.initial_quantity;
                
                // Calculate and display margin
                calculateEditMargin();
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
                modal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: data.message
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An error occurred: ' + error.message
            });
            console.error('Error:', error);
        });
}

// Update Product
function updateProduct() {
    const form = document.getElementById('editProductForm');
    const formData = new FormData(form);
    
    fetch('update_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
            modal.hide();
            
            // Reload products table
            loadProducts();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred: ' + error.message
        });
        console.error('Error:', error);
    });
}

// Delete Product
function deleteProduct(productId, productDescription) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to delete "${productDescription}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('product_id', productId);
            
            fetch('delete_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Reload products table
                    loadProducts();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred: ' + error.message
                });
                console.error('Error:', error);
            });
        }
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Load categories first, then products
    loadCategories();
    loadProducts();
    
    // Add Product form submission
    document.getElementById('submitAddProduct').addEventListener('click', function() {
        const form = document.getElementById('addProductForm');
        if (form.checkValidity()) {
            addProduct();
        } else {
            form.reportValidity();
        }
    });
    
    // Edit Product form submission
    document.getElementById('submitEditProduct').addEventListener('click', function() {
        const form = document.getElementById('editProductForm');
        if (form.checkValidity()) {
            updateProduct();
        } else {
            form.reportValidity();
        }
    });
    
    // Auto-calculate margin for Add Product form
    document.getElementById('addUnitCost').addEventListener('input', calculateAddMargin);
    document.getElementById('addSellingPrice').addEventListener('input', calculateAddMargin);
    
    // Auto-calculate margin for Edit Product form
    document.getElementById('editUnitCost').addEventListener('input', calculateEditMargin);
    document.getElementById('editSellingPrice').addEventListener('input', calculateEditMargin);
    
    // Reset Add Product form when modal is closed
    document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addProductForm').reset();
        document.getElementById('addCategory').value = '';
        document.getElementById('addUnit').value = '';
        document.getElementById('addMarginDisplay').textContent = '₱0.00';
        document.getElementById('addMarginDisplay').className = 'margin-display';
    });
});

