<?php
$models = [
    'User' => "    protected \$fillable = ['profile', 'name', 'email', 'password', 'role', 'gender', 'phone', 'address'];\n",
    'Category' => "    protected \$fillable = ['name'];\n",
    'Product' => "    protected \$fillable = ['name', 'category_id', 'description', 'usage', 'side_effects', 'dosage', 'unit_id', 'minimum_quantity', 'reorder_status', 'is_expired'];\n    public function category() { return \$this->belongsTo(Category::class); }\n    public function unit() { return \$this->belongsTo(Unit::class); }\n    public function pictures() { return \$this->hasMany(Picture::class); }\n    public function productHistories() { return \$this->hasMany(ProductHistory::class); }",
    'Picture' => "    protected \$fillable = ['product_id', 'image_path', 'is_primary'];",
    'Cart' => "    protected \$fillable = ['user_id'];\n    public function items() { return \$this->hasMany(CartItem::class); }",
    'CartItem' => "    protected \$fillable = ['cart_id', 'product_id', 'quantity'];\n    public function product() { return \$this->belongsTo(Product::class); }",
    'Order' => "    protected \$fillable = ['user_id', 'address_id', 'total_amount', 'slip_image', 'status'];\n    public function products() { return \$this->hasMany(OrderProduct::class); }",
];
foreach ($models as $name => $content) {
    $path = "app/Models/{$name}.php";
    if (file_exists($path)) {
        $file = file_get_contents($path);
        $file = str_replace("use HasFactory;", "use HasFactory;\n\n{$content}\n", $file);
        file_put_contents($path, $file);
    }
}
echo "Models updated\n";
