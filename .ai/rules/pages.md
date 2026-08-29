---
paths:
  - 'app/Http/Controllers/ProductController.php,app/Http/Requests/Catalog/**,resources/js/pages/products.tsx'
---

# Pages

## Store product images as optimized scoped WebP
Validate JPG/PNG/WebP uploads (max 5 MB, max 2400px), then orient, scale down to 1600px, and optimize to WebP quality 80 on the public disk. Persist only the relative tenants/{tenant}/outlets/{outlet}/products path; expose a derived image_url to Inertia, never image_path.
