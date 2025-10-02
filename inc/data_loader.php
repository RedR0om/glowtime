<?php
/**
 * Custom Data Loader for AI Chatbot
 * Handles loading and processing of JSON data files
 */

class SalonDataLoader {
    private static $cache = [];
    
    /**
     * Load JSON data from file with caching
     */
    public static function loadJsonData($filename) {
        if (isset(self::$cache[$filename])) {
            return self::$cache[$filename];
        }
        
        $filepath = __DIR__ . '/../data/' . $filename;
        if (!file_exists($filepath)) {
            return null;
        }
        
        $content = file_get_contents($filepath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Error in $filename: " . json_last_error_msg());
            return null;
        }
        
        self::$cache[$filename] = $data;
        return $data;
    }
    
    /**
     * Get salon services data
     */
    public static function getServicesData() {
        return self::loadJsonData('salon_services.json');
    }
    
    /**
     * Get salon knowledge base
     */
    public static function getKnowledgeData() {
        return self::loadJsonData('salon_knowledge.json');
    }
    
    /**
     * Get services by category
     */
    public static function getServicesByCategory($category) {
        $data = self::getServicesData();
        if (!$data || !isset($data['services'])) {
            return [];
        }
        
        return array_filter($data['services'], function($service) use ($category) {
            return isset($service['category']) && $service['category'] === $category;
        });
    }
    
    /**
     * Get beauty tips by category
     */
    public static function getBeautyTips($category = null) {
        $data = self::getServicesData();
        if (!$data || !isset($data['beauty_tips'])) {
            return [];
        }
        
        if ($category && isset($data['beauty_tips'][$category])) {
            return $data['beauty_tips'][$category];
        }
        
        return $data['beauty_tips'];
    }
    
    /**
     * Get seasonal recommendations
     */
    public static function getSeasonalRecommendations($season = null) {
        $data = self::getServicesData();
        if (!$data || !isset($data['seasonal_recommendations'])) {
            return [];
        }
        
        if ($season && isset($data['seasonal_recommendations'][$season])) {
            return $data['seasonal_recommendations'][$season];
        }
        
        return $data['seasonal_recommendations'];
    }
    
    /**
     * Get salon information
     */
    public static function getSalonInfo() {
        $data = self::getKnowledgeData();
        return $data['salon_info'] ?? [];
    }
    
    /**
     * Get product recommendations
     */
    public static function getProductRecommendations($category = null) {
        $data = self::getKnowledgeData();
        if (!$data || !isset($data['product_recommendations'])) {
            return [];
        }
        
        if ($category && isset($data['product_recommendations'][$category])) {
            return $data['product_recommendations'][$category];
        }
        
        return $data['product_recommendations'];
    }
    
    /**
     * Get common questions and answers
     */
    public static function getCommonQuestions($category = null) {
        $data = self::getKnowledgeData();
        if (!$data || !isset($data['common_questions'])) {
            return [];
        }
        
        if ($category && isset($data['common_questions'][$category])) {
            return $data['common_questions'][$category];
        }
        
        return $data['common_questions'];
    }
    
    /**
     * Build comprehensive context for AI
     */
    public static function buildAIContext($userHistory = []) {
        $context = [];
        
        // Add salon information
        $salonInfo = self::getSalonInfo();
        if ($salonInfo) {
            $context['salon'] = $salonInfo;
        }
        
        // Add services data
        $services = self::getServicesData();
        if ($services) {
            $context['services'] = $services;
        }
        
        // Add beauty tips
        $tips = self::getBeautyTips();
        if ($tips) {
            $context['tips'] = $tips;
        }
        
        // Add seasonal recommendations
        $season = strtolower(date('F'));
        $seasonalRecs = self::getSeasonalRecommendations();
        if ($seasonalRecs) {
            $context['seasonal'] = $seasonalRecs;
        }
        
        // Add user history if provided
        if (!empty($userHistory)) {
            $context['user_history'] = $userHistory;
        }
        
        return $context;
    }
    
    /**
     * Format context for AI prompt
     */
    public static function formatContextForAI($context) {
        $formatted = [];
        
        if (isset($context['salon'])) {
            $salon = $context['salon'];
            $formatted[] = "Salon Information: {$salon['name']} - {$salon['specialties'][0]} specialist with {$salon['experience_years']} years experience";
        }
        
        if (isset($context['services']['services'])) {
            $services = array_map(function($s) {
                return "{$s['name']}: {$s['description']} (\${$s['price']}, {$s['duration']}min)";
            }, $context['services']['services']);
            $formatted[] = "Available Services: " . implode(', ', $services);
        }
        
        if (isset($context['tips'])) {
            $allTips = [];
            foreach ($context['tips'] as $category => $tipList) {
                $allTips = array_merge($allTips, $tipList);
            }
            $formatted[] = "Beauty Tips: " . implode(', ', $allTips);
        }
        
        if (isset($context['user_history'])) {
            $formatted[] = "Client History: " . implode(', ', $context['user_history']);
        }
        
        return implode("\n", $formatted);
    }
}
?>
