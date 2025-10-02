# Custom Data Integration Guide for Glowtime AI Chatbot

## 🎯 Overview

Your AI chatbot now supports custom data integration through JSON files, allowing you to feed specific salon information, services, beauty tips, and knowledge directly to the AI for more accurate and personalized responses.

## 📁 File Structure

```
data/
├── salon_services.json      # Services, tips, seasonal recommendations
├── salon_knowledge.json     # Salon info, products, FAQ
└── (your custom files)      # Add your own JSON files here

inc/
├── data_loader.php          # Data loading and processing functions
└── bootstrap.php            # Core system functions
```

## 🔧 How It Works

### 1. **JSON Data Files**
Store your custom data in structured JSON files in the `data/` directory:

**Example: `data/salon_services.json`**
```json
{
  "services": [
    {
      "id": 1,
      "name": "Haircut & Styling",
      "description": "Professional haircut with modern styling",
      "duration": 60,
      "price": 45.00,
      "category": "hair",
      "trends": ["layered cuts", "face-framing"],
      "recommended_for": ["all hair types", "professional look"],
      "tips": ["Regular trims every 6-8 weeks", "Layered cuts add volume"]
    }
  ],
  "beauty_tips": {
    "hair_care": ["Use sulfate-free shampoo", "Deep condition weekly"],
    "skincare": ["Always remove makeup", "Use SPF daily"]
  }
}
```

### 2. **Data Loader Class**
The `SalonDataLoader` class provides methods to access your custom data:

```php
// Load services data
$services = SalonDataLoader::getServicesData();

// Get services by category
$hairServices = SalonDataLoader::getServicesByCategory('hair');

// Get beauty tips
$tips = SalonDataLoader::getBeautyTips('hair_care');

// Build AI context
$context = SalonDataLoader::buildAIContext($userHistory);
```

### 3. **AI Integration**
The AI automatically uses your custom data when responding to client questions:

```php
// Enhanced context with custom data
$customContext = SalonDataLoader::buildAIContext($historyItems);
$formattedContext = SalonDataLoader::formatContextForAI($customContext);
$response = generateAIResponse($message, $formattedContext);
```

## 🚀 Adding Your Own Data

### Method 1: Direct JSON File Creation

1. **Create a new JSON file** in the `data/` directory
2. **Structure your data** according to your needs
3. **Add loading method** to `SalonDataLoader` class
4. **Update AI context** to include your data

**Example: `data/your_custom_data.json`**
```json
{
  "products": [
    {
      "name": "Shampoo Brand X",
      "type": "hair_care",
      "benefits": ["color protection", "moisturizing"],
      "price": 25.00
    }
  ],
  "treatments": [
    {
      "name": "Keratin Treatment",
      "duration": 120,
      "results": "Smoother, shinier hair for 3-4 months"
    }
  ]
}
```

### Method 2: Database Integration

You can also load data from your database:

```php
// In your data loader
public static function getDatabaseServices() {
    $pdo = pdo();
    $stmt = $pdo->query("SELECT * FROM services");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Convert to AI context
$dbServices = self::getDatabaseServices();
$context = self::buildAIContext($dbServices);
```

### Method 3: Real-time Data Updates

Update your JSON files dynamically:

```php
// Add new service to JSON
$services = SalonDataLoader::getServicesData();
$newService = [
    'id' => count($services['services']) + 1,
    'name' => 'New Service',
    'description' => 'Service description',
    // ... other fields
];
$services['services'][] = $newService;

// Save back to file
file_put_contents('data/salon_services.json', json_encode($services, JSON_PRETTY_PRINT));
```

## 🎨 Custom Data Types You Can Add

### 1. **Services & Treatments**
- Service descriptions and pricing
- Duration and availability
- Category and specialization
- Trends and recommendations

### 2. **Beauty Knowledge**
- Hair care tips and routines
- Skincare advice and products
- Nail care techniques
- Seasonal beauty recommendations

### 3. **Salon Information**
- Staff expertise and specialties
- Awards and certifications
- Salon policies and procedures
- Contact information and hours

### 4. **Product Recommendations**
- Brand suggestions
- Product benefits and features
- Price ranges and availability
- Usage instructions

### 5. **FAQ & Common Questions**
- Frequently asked questions
- Detailed answers and explanations
- Related services and products
- Booking and appointment info

## 🔄 Dynamic Data Updates

### Admin Interface
Use the admin interface at `admin_custom_data.php` to:
- Add new services
- Update beauty tips
- Manage salon information
- Test data integration

### Programmatic Updates
```php
// Update existing data
$services = SalonDataLoader::getServicesData();
$services['services'][0]['price'] = 50.00; // Update price
file_put_contents('data/salon_services.json', json_encode($services, JSON_PRETTY_PRINT));

// Clear cache
SalonDataLoader::$cache = [];
```

## 🧪 Testing Your Data

### 1. **Test Data Loading**
Visit `test_custom_data.php` to verify:
- JSON files load correctly
- Data structure is valid
- AI context builds properly

### 2. **Test AI Responses**
Use the chatbot at `client_ai.php` to test:
- Personalized responses
- Service recommendations
- Beauty advice accuracy
- Context awareness

### 3. **Monitor Performance**
Check error logs for:
- JSON parsing errors
- API call failures
- Data loading issues

## 📊 Best Practices

### 1. **Data Structure**
- Keep JSON files well-organized
- Use consistent field names
- Include relevant metadata
- Validate data before saving

### 2. **Performance**
- Use caching for frequently accessed data
- Minimize file I/O operations
- Optimize JSON file sizes
- Consider database for large datasets

### 3. **Maintenance**
- Regular data updates
- Backup JSON files
- Monitor AI response quality
- Update context as needed

## 🎯 Example Use Cases

### 1. **Seasonal Recommendations**
```json
{
  "seasonal_recommendations": {
    "spring": {
      "hair": "Light layers and pastel highlights",
      "skincare": "Brightening treatments",
      "nails": "Pastel colors"
    }
  }
}
```

### 2. **Product Knowledge**
```json
{
  "products": {
    "shampoo": ["Brand A", "Brand B"],
    "conditioner": ["Brand C", "Brand D"],
    "styling": ["Product X", "Product Y"]
  }
}
```

### 3. **Expertise Areas**
```json
{
  "stylists": [
    {
      "name": "Sarah",
      "specialty": "color",
      "experience": "8 years",
      "certifications": ["Master Colorist"]
    }
  ]
}
```

## 🚀 Advanced Features

### 1. **Multi-language Support**
Add language-specific data:
```json
{
  "services": {
    "en": {"name": "Haircut", "description": "Professional haircut"},
    "es": {"name": "Corte de Cabello", "description": "Corte profesional"}
  }
}
```

### 2. **A/B Testing**
Test different responses:
```json
{
  "responses": {
    "hair_color": [
      "Response A: Natural highlights work well...",
      "Response B: Consider balayage for a sun-kissed look..."
    ]
  }
}
```

### 3. **Analytics Integration**
Track which data is most useful:
```php
// Log AI context usage
error_log("AI Context: " . $formattedContext);
error_log("User Question: " . $message);
error_log("AI Response: " . $response);
```

Your AI chatbot now has access to comprehensive, customizable data that makes it much more intelligent and helpful for your salon clients! 🎉
