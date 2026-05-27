<?php

namespace App\Support;

class WorkflowTemplates
{
    public static function all(): array
    {
        return [
            self::allNodesDemo(),
            self::welcomeAutoReply(),
            self::keywordFaq(),
            self::aiSupportAssistant(),
            self::leadCaptureApi(),
            self::orderStatusInquiry(),
        ];
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $template) {
            if ($template['slug'] === $slug) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Master demo: Trigger → Condition → API → AI → Send Message
     */
    public static function allNodesDemo(): array
    {
        return [
            'slug' => 'all-nodes-demo',
            'name' => '📚 Demo: All Node Types',
            'description' => 'Learn how every node works — Trigger, Condition, API, AI, and Send Message in one linear flow. Start here!',
            'category' => 'tutorial',
            'trigger_type' => 'message_received',
            'definition' => [
                'nodes' => [
                    [
                        'id' => 'trigger-1',
                        'type' => 'trigger',
                        'position' => ['x' => 200, 'y' => 40],
                        'data' => [
                            'label' => 'Message Received',
                            'summary' => 'Runs when a customer sends any WhatsApp text',
                        ],
                    ],
                    [
                        'id' => 'condition-1',
                        'type' => 'condition',
                        'position' => ['x' => 200, 'y' => 160],
                        'data' => [
                            'label' => 'Condition',
                            'summary' => 'Passes all messages (empty keyword = always true)',
                            'field' => 'message',
                            'operator' => 'contains',
                            'value' => '',
                        ],
                    ],
                    [
                        'id' => 'api-1',
                        'type' => 'api',
                        'position' => ['x' => 200, 'y' => 280],
                        'data' => [
                            'label' => 'API Request',
                            'summary' => 'POST customer phone to a webhook (replace URL)',
                            'url' => 'https://jsonplaceholder.typicode.com/posts',
                            'method' => 'POST',
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => [
                                'title' => 'WhatsApp lead from {{contact_phone}}',
                                'body' => 'Message: {{message}}',
                            ],
                            'timeout' => 15,
                            'retries' => 1,
                            'use_fallback' => true,
                        ],
                    ],
                    [
                        'id' => 'ai-1',
                        'type' => 'ai',
                        'position' => ['x' => 200, 'y' => 400],
                        'data' => [
                            'label' => 'AI Response',
                            'summary' => 'Generate a friendly reply using AI',
                            'provider' => 'openrouter',
                            'model' => 'openai/gpt-4o-mini',
                            'prompt' => 'You are a helpful WhatsApp assistant for a business. Reply briefly and professionally to this customer message: "{{message}}". Customer name: {{contact_name}}.',
                            'temperature' => 0.7,
                            'max_tokens' => 200,
                            'fallback_message' => 'Thanks for your message! Our team will get back to you shortly.',
                        ],
                    ],
                    [
                        'id' => 'send-1',
                        'type' => 'send_message',
                        'position' => ['x' => 200, 'y' => 520],
                        'data' => [
                            'label' => 'Send WhatsApp Reply',
                            'summary' => 'Sends the AI response back on WhatsApp',
                            'message' => '{{ai_response}}',
                        ],
                    ],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'condition-1'],
                    ['id' => 'e2', 'source' => 'condition-1', 'target' => 'api-1'],
                    ['id' => 'e3', 'source' => 'api-1', 'target' => 'ai-1'],
                    ['id' => 'e4', 'source' => 'ai-1', 'target' => 'send-1'],
                ],
            ],
        ];
    }

    public static function welcomeAutoReply(): array
    {
        return [
            'slug' => 'welcome-auto-reply',
            'name' => '👋 Welcome Auto-Reply',
            'description' => 'Instantly greet customers when they message you for the first time.',
            'category' => 'common',
            'trigger_type' => 'message_received',
            'definition' => self::linearFlow(
                [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'y' => 80, 'data' => [
                        'label' => 'Message Received',
                        'summary' => 'Fires on every incoming message',
                    ]],
                    ['id' => 'send-1', 'type' => 'send_message', 'y' => 220, 'data' => [
                        'label' => 'Welcome Message',
                        'summary' => 'Sends greeting text',
                        'message' => "Hello {{contact_name}}! 👋\n\nThanks for reaching out. We're here to help. Reply with your question and our team will assist you shortly.\n\n— WhatsFlow Team",
                    ]],
                ]
            ),
        ];
    }

    public static function keywordFaq(): array
    {
        return [
            'slug' => 'keyword-faq',
            'name' => '❓ Keyword FAQ Bot',
            'description' => 'Auto-answer common questions when customers type keywords like "hours", "price", or "location".',
            'category' => 'common',
            'trigger_type' => 'message_received',
            'definition' => self::linearFlow(
                [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'y' => 80, 'data' => [
                        'label' => 'Message Received',
                        'summary' => 'Listens for customer messages',
                    ]],
                    ['id' => 'condition-1', 'type' => 'condition', 'y' => 200, 'data' => [
                        'label' => 'Check Keyword',
                        'summary' => 'Matches if message contains "hours"',
                        'field' => 'message',
                        'operator' => 'contains',
                        'value' => 'hours',
                    ]],
                    ['id' => 'send-1', 'type' => 'send_message', 'y' => 320, 'data' => [
                        'label' => 'FAQ Answer',
                        'summary' => 'Sends business hours info',
                        'message' => "🕐 Our business hours:\nMon–Fri: 9:00 AM – 6:00 PM\nSat: 10:00 AM – 2:00 PM\nSun: Closed\n\nReply *price* or *location* for more info!",
                    ]],
                ]
            ),
        ];
    }

    public static function aiSupportAssistant(): array
    {
        return [
            'slug' => 'ai-support-assistant',
            'name' => '🤖 AI Support Assistant',
            'description' => 'Let AI read the customer message and send an intelligent, context-aware reply automatically.',
            'category' => 'common',
            'trigger_type' => 'message_received',
            'definition' => self::linearFlow(
                [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'y' => 80, 'data' => [
                        'label' => 'Message Received',
                        'summary' => 'Incoming customer message',
                    ]],
                    ['id' => 'ai-1', 'type' => 'ai', 'y' => 200, 'data' => [
                        'label' => 'AI Support',
                        'summary' => 'Generates support reply',
                        'provider' => 'openrouter',
                        'model' => 'openai/gpt-4o-mini',
                        'prompt' => 'You are a customer support agent. Answer this WhatsApp message helpfully in 2-3 sentences: "{{message}}"',
                        'temperature' => 0.6,
                        'max_tokens' => 250,
                        'fallback_message' => 'Thanks for contacting us! A human agent will reply soon.',
                    ]],
                    ['id' => 'send-1', 'type' => 'send_message', 'y' => 340, 'data' => [
                        'label' => 'Send AI Reply',
                        'summary' => 'Delivers AI response on WhatsApp',
                        'message' => '{{ai_response}}',
                    ]],
                ]
            ),
        ];
    }

    public static function leadCaptureApi(): array
    {
        return [
            'slug' => 'lead-capture-api',
            'name' => '📋 Lead Capture (API)',
            'description' => 'Send contact details to your CRM or Google Sheet via API, then confirm to the customer on WhatsApp.',
            'category' => 'common',
            'trigger_type' => 'message_received',
            'definition' => self::linearFlow(
                [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'y' => 80, 'data' => [
                        'label' => 'Message Received',
                        'summary' => 'New lead message',
                    ]],
                    ['id' => 'api-1', 'type' => 'api', 'y' => 200, 'data' => [
                        'label' => 'Save to CRM',
                        'summary' => 'POST lead to your webhook — replace URL',
                        'url' => 'https://your-crm.com/api/leads',
                        'method' => 'POST',
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer YOUR_API_KEY',
                        ],
                        'body' => [
                            'phone' => '{{contact_phone}}',
                            'name' => '{{contact_name}}',
                            'message' => '{{message}}',
                            'source' => 'whatsapp',
                        ],
                        'timeout' => 15,
                        'retries' => 2,
                        'use_fallback' => true,
                    ]],
                    ['id' => 'send-1', 'type' => 'send_message', 'y' => 340, 'data' => [
                        'label' => 'Confirmation',
                        'summary' => 'Thanks the customer',
                        'message' => "Hi {{contact_name}}! ✅\n\nWe've received your message and saved your details. Our sales team will contact you within 24 hours.",
                    ]],
                ]
            ),
        ];
    }

    public static function orderStatusInquiry(): array
    {
        return [
            'slug' => 'order-status-inquiry',
            'name' => '📦 Order Status Lookup',
            'description' => 'When customers ask about orders, call your API and reply with status — uses Condition + API + Send Message.',
            'category' => 'common',
            'trigger_type' => 'message_received',
            'definition' => self::linearFlow(
                [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'y' => 80, 'data' => [
                        'label' => 'Message Received',
                        'summary' => 'Customer sends a message',
                    ]],
                    ['id' => 'condition-1', 'type' => 'condition', 'y' => 200, 'data' => [
                        'label' => 'Order Keyword',
                        'summary' => 'Checks for "order" in message',
                        'field' => 'message',
                        'operator' => 'contains',
                        'value' => 'order',
                    ]],
                    ['id' => 'api-1', 'type' => 'api', 'y' => 320, 'data' => [
                        'label' => 'Fetch Order Status',
                        'summary' => 'GET order from your system — replace URL',
                        'url' => 'https://your-store.com/api/orders?phone={{contact_phone}}',
                        'method' => 'GET',
                        'headers' => ['Accept' => 'application/json'],
                        'body' => [],
                        'timeout' => 10,
                        'retries' => 1,
                        'use_fallback' => true,
                    ]],
                    ['id' => 'send-1', 'type' => 'send_message', 'y' => 440, 'data' => [
                        'label' => 'Status Reply',
                        'summary' => 'Sends order update to customer',
                        'message' => "Hi {{contact_name}}! 📦\n\nWe're checking your order status. If you have an order ID, please share it and we'll update you right away.\n\nFor urgent help, call our support line.",
                    ]],
                ]
            ),
        ];
    }

    private static function linearFlow(array $nodeDefs): array
    {
        $nodes = [];
        $edges = [];

        foreach ($nodeDefs as $i => $def) {
            $nodes[] = [
                'id' => $def['id'],
                'type' => $def['type'],
                'position' => ['x' => 200, 'y' => $def['y']],
                'data' => $def['data'],
            ];

            if ($i > 0) {
                $prev = $nodeDefs[$i - 1]['id'];
                $edges[] = [
                    'id' => 'e'.$i,
                    'source' => $prev,
                    'target' => $def['id'],
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
