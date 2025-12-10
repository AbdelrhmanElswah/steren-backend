<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationType;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseNotificationService
{
    /**
     * Create a notification record in the database without sending push notification
     * 
     * @param int $userId
     * @param string $type
     * @param string|array $title - Can be a string (backward compatibility) or array with 'en', 'ar', 'kr' keys
     * @param string|array $message - Can be a string (backward compatibility) or array with 'en', 'ar', 'kr' keys
     * @param array $data
     * @param int|null $notificationTypeId
     * @return Notification
     */
    public function createNotification(
        int $userId,
        string $type,
        string|array $title,
        string|array $message,
        array $data = [],
        ?int $notificationTypeId = null
    ): Notification {
        try {
            // Handle title - support both string (backward compatibility) and array (translations)
            // title field is used for English, title_ar and title_kr for translations
            $titleData = [];
            if (is_array($title)) {
                $titleData = [
                    'title' => $title['en'] ?? null, // English stored in title field
                    'title_ar' => $title['ar'] ?? null,
                    'title_kr' => $title['kr'] ?? null,
                ];
            } else {
                // Backward compatibility: use string as English title
                $titleData = [
                    'title' => $title, // English stored in title field
                    'title_ar' => null,
                    'title_kr' => null,
                ];
            }

            // Handle message - support both string (backward compatibility) and array (translations)
            // message field is used for English, message_ar and message_kr for translations
            $messageData = [];
            if (is_array($message)) {
                $messageData = [
                    'message' => $message['en'] ?? null, // English stored in message field
                    'message_ar' => $message['ar'] ?? null,
                    'message_kr' => $message['kr'] ?? null,
                ];
            } else {
                // Backward compatibility: use string as English message
                $messageData = [
                    'message' => $message, // English stored in message field
                    'message_ar' => null,
                    'message_kr' => null,
                ];
            }

            $notification = Notification::create(array_merge([
                'user_id' => $userId,
                'notification_type_id' => $notificationTypeId,
                'type' => $type,
                'data' => $data,
                'is_read' => false,
                'read_at' => null,
            ], $titleData, $messageData));

            Log::info('Notification created in database', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
                'title' => is_array($title) ? ($title['en'] ?? 'N/A') : $title
            ]);

            return $notification;

        } catch (Exception $e) {
            Log::error('Failed to create notification in database', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create notification using notification type
     */
    public function createNotificationByType(
        int $userId,
        int $notificationTypeId,
        array $templateData = []
    ): Notification {
        try {
            $notificationType = NotificationType::findOrFail($notificationTypeId);
            
            // Replace template variables if template exists
            $title = $notificationType->default_template['title'] ?? 'Notification';
            $message = $notificationType->default_template['body'] ?? '';
            
            if (!empty($templateData)) {
                $title = $this->replaceTemplateVariables($title, $templateData);
                $message = $this->replaceTemplateVariables($message, $templateData);
            }

            return $this->createNotification(
                $userId,
                $notificationType->name,
                $title,
                $message,
                $templateData
            );

        } catch (Exception $e) {
            Log::error('Failed to create notification by type', [
                'user_id' => $userId,
                'notification_type_id' => $notificationTypeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing created notification
     */
    public function createListingCreatedNotification($listing): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_created');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_created" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            // Get translations
            $translations = $this->getNotificationTranslations('listing_created', ['car_name' => $carName]);

            return $this->createNotification(
                $listing->user_id,
                'listing_created',
                $translations['title'],
                $translations['message'],
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'price' => $listing->price,
                    'currency' => $listing->price_currency ?? 'USD',
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing created notification', [
                'listing_id' => $listing->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing approved notification
     */
    public function createListingApprovedNotification($listing): Notification
    {
        try {
            // Validate listing has a user_id
            if (!$listing->user_id) {
                throw new Exception('Cannot create notification: listing has no user_id. Listing ID: ' . ($listing->id ?? 'unknown'));
            }

            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_approved');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_approved" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            // Get translations
            $translations = $this->getNotificationTranslations('listing_approved', ['car_name' => $carName]);

            // Ensure user_id is an integer
            $userId = (int) $listing->user_id;
            if ($userId <= 0) {
                throw new Exception('Invalid user_id for listing notification: ' . $listing->user_id);
            }

            return $this->createNotification(
                $userId,
                'listing_approved',
                $translations['title'],
                $translations['message'],
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'price' => $listing->price,
                    'currency' => $listing->price_currency ?? 'USD',
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing approved notification', [
                'listing_id' => $listing->id ?? null,
                'user_id' => $listing->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create listing rejected notification
     */
    public function createListingRejectedNotification($listing, string $reason = null): Notification
    {
        try {
            // Validate listing has a user_id
            if (!$listing->user_id) {
                throw new Exception('Cannot create notification: listing has no user_id. Listing ID: ' . ($listing->id ?? 'unknown'));
            }

            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('listing_rejected');
            if (!$notificationType) {
                throw new Exception('Notification type "listing_rejected" not found. Please run the seeder.');
            }

            $carName = $listing->title ?? 'Your listing';
            if ($listing->brand && $listing->carModel) {
                $carName = $listing->brand->name . ' ' . $listing->carModel->name;
                if ($listing->year) {
                    $carName .= ' ' . $listing->year;
                }
            }

            $reasonText = $reason ?? 'No reason provided';
            
            // Get translations
            $translations = $this->getNotificationTranslations('listing_rejected', [
                'car_name' => $carName,
                'reason' => $reasonText,
            ]);

            // Ensure user_id is an integer
            $userId = (int) $listing->user_id;
            if ($userId <= 0) {
                throw new Exception('Invalid user_id for listing notification: ' . $listing->user_id);
            }

            return $this->createNotification(
                $userId,
                'listing_rejected',
                $translations['title'],
                $translations['message'],
                [
                    'car_listing_id' => $listing->id,
                    'car_name' => $carName,
                    'reason' => $reasonText,
                    'price' => $listing->price ?? null,
                    'currency' => $listing->price_currency ?? 'USD',
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create listing rejected notification', [
                'listing_id' => $listing->id ?? null,
                'user_id' => $listing->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Replace template variables with actual data
     */
    private function replaceTemplateVariables(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /**
     * Get notification translations for a given type and dynamic data
     * 
     * @param string $type Notification type
     * @param array $data Dynamic data for template replacement (e.g., car_name, company_name, reason)
     * @return array Array with 'title' and 'message' keys, each containing ['en', 'ar', 'kr']
     */
    private function getNotificationTranslations(string $type, array $data = []): array
    {
        $translations = [
            'listing_created' => [
                'title' => [
                    'en' => 'Listing Created Successfully!',
                    'ar' => 'تم إنشاء الإعلان بنجاح!',
                    'kr' => 'ئیلان بە سەرکەوتوویی دروست کرا!',
                ],
                'message' => [
                    'en' => "Your {{car_name}} listing has been created and is pending approval.",
                    'ar' => "تم إنشاء إعلان {{car_name}} الخاص بك وهو في انتظار الموافقة.",
                    'kr' => "ئیلانی {{car_name}} ت دروست کرا و چاوەڕوانی پەسندکردنە.",
                ],
            ],
            'listing_approved' => [
                'title' => [
                    'en' => 'Listing Approved!',
                    'ar' => 'تمت الموافقة على الإعلان!',
                    'kr' => 'ئیلان پەسند کرا!',
                ],
                'message' => [
                    'en' => "Great news! Your {{car_name}} listing has been approved and is now live. Buyers can now see and contact you about this vehicle.",
                    'ar' => "أخبار رائعة! تمت الموافقة على إعلان {{car_name}} الخاص بك وهو الآن نشط. يمكن للمشترين الآن رؤية والاتصال بك بخصوص هذه المركبة.",
                    'kr' => "هەواڵی خۆش! ئیلانی {{car_name}} ت پەسند کرا و ئێستا زیندووە. کڕیاران دەتوانن ئێستا ببینن و پەیوەندی بەتەوە بکەن لەسەر ئەم ئۆتۆمبێلە.",
                ],
            ],
            'listing_rejected' => [
                'title' => [
                    'en' => 'Listing Rejected',
                    'ar' => 'تم رفض الإعلان',
                    'kr' => 'ئیلان ڕەتکرایەوە',
                ],
                'message' => [
                    'en' => "Your {{car_name}} listing was not approved. Reason: {{reason}}. Please review the requirements and submit a new listing.",
                    'ar' => "لم تتم الموافقة على إعلان {{car_name}} الخاص بك. السبب: {{reason}}. يرجى مراجعة المتطلبات وإرسال إعلان جديد.",
                    'kr' => "ئیلانی {{car_name}} ت پەسند نەکرا. هۆکار: {{reason}}. تکایە پێداچوونەوەی پێداویستییەکان بکە و ئیلانێکی نوێ بنێرە.",
                ],
            ],
            'dealer_application_submitted' => [
                'title' => [
                    'en' => 'Application Submitted!',
                    'ar' => 'تم إرسال الطلب!',
                    'kr' => 'داواکاری نێردرا!',
                ],
                'message' => [
                    'en' => "Your dealer application for {{company_name}} has been submitted and is pending review.",
                    'ar' => "تم إرسال طلب التاجر الخاص بك لـ {{company_name}} وهو في انتظار المراجعة.",
                    'kr' => "داواکاری دەستفرۆشی {{company_name}} ت نێردرا و چاوەڕوانی پێداچوونەوەیە.",
                ],
            ],
            'dealer_application_approved' => [
                'title' => [
                    'en' => 'Dealer Application Approved!',
                    'ar' => 'تمت الموافقة على طلب التاجر!',
                    'kr' => 'داواکاری دەستفرۆش پەسند کرا!',
                ],
                'message' => [
                    'en' => "Congratulations! Your dealer application has been approved. You can now post car listings and manage your dealership.",
                    'ar' => "تهانينا! تمت الموافقة على طلب التاجر الخاص بك. يمكنك الآن نشر إعلانات السيارات وإدارة وكالتك.",
                    'kr' => "پیرۆزە! داواکاری دەستفرۆش ت پەسند کرا. ئێستا دەتوانیت ئیلانی ئۆتۆمبێل بنێریت و دەستفرۆشخانەکەت بەڕێوەبەریت.",
                ],
            ],
            'dealer_application_rejected' => [
                'title' => [
                    'en' => 'Dealer Application Rejected',
                    'ar' => 'تم رفض طلب التاجر',
                    'kr' => 'داواکاری دەستفرۆش ڕەتکرایەوە',
                ],
                'message' => [
                    'en' => "Your dealer application was not approved. Reason: {{reason}}. Please review the requirements and submit a new application.",
                    'ar' => "لم تتم الموافقة على طلب التاجر الخاص بك. السبب: {{reason}}. يرجى مراجعة المتطلبات وإرسال طلب جديد.",
                    'kr' => "داواکاری دەستفرۆش ت پەسند نەکرا. هۆکار: {{reason}}. تکایە پێداچوونەوەی پێداویستییەکان بکە و داواکارییەکی نوێ بنێرە.",
                ],
            ],
            'user_created' => [
                'title' => [
                    'en' => 'Welcome to Steren!',
                    'ar' => 'مرحباً بك في ستيرين!',
                    'kr' => 'بەخێربێیت بۆ ستێرین!',
                ],
                'message' => [
                    'en' => "Welcome {{user_name}}! We're excited to have you join our community. Start exploring amazing cars or list your own!",
                    'ar' => "مرحباً {{user_name}}! نحن متحمسون لانضمامك إلى مجتمعنا. ابدأ في استكشاف السيارات الرائعة أو أضف سيارتك الخاصة!",
                    'kr' => "بەخێربێیت {{user_name}}! دڵخۆشین کە بەشداری کۆمەڵگاکەمان دەکەیت. دەست بکە بە گەڕان بەدوای ئۆتۆمبێلی سەرسوڕهێنەر یان ئۆتۆمبێلەکەت خۆت بنووسەوە!",
                ],
            ],
        ];

        $result = $translations[$type] ?? [
            'title' => ['en' => 'Notification', 'ar' => 'إشعار', 'kr' => 'ئاگاداری'],
            'message' => ['en' => 'You have a new notification.', 'ar' => 'لديك إشعار جديد.', 'kr' => 'ئاگادارییەکی نوێت هەیە.'],
        ];

        // Replace template variables in all languages
        foreach (['en', 'ar', 'kr'] as $lang) {
            if (isset($result['title'][$lang])) {
                $result['title'][$lang] = $this->replaceTemplateVariables($result['title'][$lang], $data);
            }
            if (isset($result['message'][$lang])) {
                $result['message'][$lang] = $this->replaceTemplateVariables($result['message'][$lang], $data);
            }
        }

        return $result;
    }

    /**
     * Create dealer application submitted notification
     */
    public function createDealerApplicationSubmittedNotification($application): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_submitted');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_submitted" not found. Please run the seeder.');
            }

            $companyName = $application->dealership_company_name ?? 'your dealership';
            
            // Get translations
            $translations = $this->getNotificationTranslations('dealer_application_submitted', ['company_name' => $companyName]);

            // Get user ID from relationship (DealerApplication uses partner_id, not user_id)
            $userId = $application->user?->id ?? $application->partner_id;
            if (!$userId) {
                throw new Exception('Invalid user ID for dealer application notification: user relationship not loaded and partner_id is null');
            }
            // Ensure userId is an integer
            $userId = (int) $userId;
            if ($userId <= 0) {
                throw new Exception('Invalid user ID for dealer application notification: user ID must be a positive integer');
            }

            return $this->createNotification(
                $userId,
                'dealer_application_submitted',
                $translations['title'],
                $translations['message'],
                [
                    'dealer_application_id' => $application->id,
                    'company_name' => $companyName,
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application submitted notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create dealer application approved notification
     */
    public function createDealerApplicationApprovedNotification($application): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_approved');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_approved" not found. Please run the seeder.');
            }

            // Get translations
            $translations = $this->getNotificationTranslations('dealer_application_approved');

            // Get user ID from relationship (DealerApplication uses partner_id, not user_id)
            $userId = $application->user?->id ?? $application->partner_id;
            if (!$userId) {
                throw new Exception('Invalid user ID for dealer application notification: user relationship not loaded and partner_id is null');
            }
            // Ensure userId is an integer
            $userId = (int) $userId;
            if ($userId <= 0) {
                throw new Exception('Invalid user ID for dealer application notification: user ID must be a positive integer');
            }

            return $this->createNotification(
                $userId,
                'dealer_application_approved',
                $translations['title'],
                $translations['message'],
                [
                    'dealer_application_id' => $application->id,
                    'approved_at' => $application->approved_at?->toISOString(),
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application approved notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create dealer application rejected notification
     */
    public function createDealerApplicationRejectedNotification($application, string $reason = null): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('dealer_application_rejected');
            if (!$notificationType) {
                throw new Exception('Notification type "dealer_application_rejected" not found. Please run the seeder.');
            }

            $reasonText = $reason ?? 'No reason provided';
            
            // Get translations
            $translations = $this->getNotificationTranslations('dealer_application_rejected', ['reason' => $reasonText]);

            // Get user ID from relationship (DealerApplication uses partner_id, not user_id)
            $userId = $application->user?->id ?? $application->partner_id;
            if (!$userId) {
                throw new Exception('Invalid user ID for dealer application notification: user relationship not loaded and partner_id is null');
            }
            // Ensure userId is an integer
            $userId = (int) $userId;
            if ($userId <= 0) {
                throw new Exception('Invalid user ID for dealer application notification: user ID must be a positive integer');
            }

            return $this->createNotification(
                $userId,
                'dealer_application_rejected',
                $translations['title'],
                $translations['message'],
                [
                    'dealer_application_id' => $application->id,
                    'reason' => $reasonText,
                    'rejected_at' => $application->rejected_at?->toISOString(),
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create dealer application rejected notification', [
                'application_id' => $application->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create user created (welcome) notification
     */
    public function createUserCreatedNotification($user): Notification
    {
        try {
            // Get the notification type (should exist from seeder)
            $notificationType = NotificationType::findByName('user_created');
            if (!$notificationType) {
                throw new Exception('Notification type "user_created" not found. Please run the NotificationTypeSeeder.');
            }

            $userName = $user->full_name ?? 'there';
            
            // Get translations
            $translations = $this->getNotificationTranslations('user_created', ['user_name' => $userName]);

            return $this->createNotification(
                $user->id,
                'user_created',
                $translations['title'],
                $translations['message'],
                [
                    'user_id' => $user->id,
                    'user_name' => $userName,
                ],
                $notificationType->id
            );

        } catch (Exception $e) {
            Log::error('Failed to create user created notification', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}

