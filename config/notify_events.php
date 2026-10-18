<?php

/*
|--------------------------------------------------------------------------
| Dot.Notify event catalogue
|--------------------------------------------------------------------------
| The one list of events the Dot apps send to POST /api/ingest/events, and the
| contract those apps are written against. Do NOT rename an event or a data
| key: bump `version` and tell the calling apps first.
|
| platforms.<platform key from config/ecosystem.php>.<event name> = [
|     label, description        shown on the "Connected apps" page
|     channel                   default channel type for the company's rule (email)
|     recipient                 user      = the person the event is about
|                               approvers = the people who must act on it; the
|                                           calling app sends one call per approver
|     notification_type         what the recipient can opt out of (security is
|                               never suppressed)
|     template                  default subject + body, plain text, {{ variable }}
|                               slots exactly as the template editor writes them;
|                               every variable must be one of `data`
|     data                      the ONLY keys kept from the request's `data`.
|                               Anything else is dropped before storage and
|                               rendering. A key named `url` or ending `_url` must
|                               be an absolute https URL on the calling platform's
|                               own host (config('ecosystem.platforms')), else it
|                               is dropped.
| ]
|
| A template line whose variables are all empty is left out of the message, so
| an optional field the caller did not send leaves no empty label behind.
|
| Unknown events from a known platform are still accepted (recorded as no_rule
| unless the company wrote its own rule); they are just not catalogued, so their
| data is not filtered.
*/

return [

    'version' => 1,

    // Longest string kept for any data value (longer values are cut and end in an ellipsis).
    'max_value_length' => 500,

    // Longest URL kept; a longer one is dropped, never cut.
    'max_url_length' => 2048,

    'platforms' => [

        'tasks' => [
            'task.assigned' => [
                'label' => 'Task assigned',
                'description' => 'A task was assigned to someone. Tells that person.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'New task: {{ task_title }}',
                    'body' => "You have a new task on {{ board_name }}.\n\nTask: {{ task_title }}\nAssigned by: {{ assigned_by }}\nDue: {{ due_date }}\n\nOpen the task: {{ url }}",
                ],
                'data' => ['task_title', 'board_name', 'assigned_by', 'due_date', 'url'],
            ],
            'task.due_soon' => [
                'label' => 'Task due soon',
                'description' => 'A task is close to its due date. Tells the person it is assigned to.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Due soon: {{ task_title }}',
                    'body' => "A task on {{ board_name }} is due soon.\n\nTask: {{ task_title }}\nDue: {{ due_date }}\n\nOpen the task: {{ url }}",
                ],
                'data' => ['task_title', 'board_name', 'due_date', 'url'],
            ],
            'task.escalated' => [
                'label' => 'Task escalated',
                'description' => 'A task was escalated. Tells the person it was escalated to.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Escalated: {{ task_title }}',
                    'body' => "A task on {{ board_name }} was escalated and needs attention.\n\nTask: {{ task_title }}\nOwner: {{ owner_name }}\n\nOpen the task: {{ url }}",
                ],
                'data' => ['task_title', 'board_name', 'owner_name', 'url'],
            ],
            'task.mentioned' => [
                'label' => 'Mentioned on a task',
                'description' => 'Someone mentioned a person on a task. Tells the person mentioned.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'You were mentioned on {{ task_title }}',
                    'body' => "You were mentioned on a task on {{ board_name }}.\n\nTask: {{ task_title }}\nMentioned by: {{ mentioned_by }}\n\nOpen the task: {{ url }}",
                ],
                'data' => ['task_title', 'board_name', 'mentioned_by', 'url'],
            ],
        ],

        'hr' => [
            'leave.requested' => [
                'label' => 'Leave requested',
                'description' => 'An employee asked for leave. Tells the people who approve it. The reason for the leave is never sent.',
                'channel' => 'email',
                'recipient' => 'approvers',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Leave request: {{ employee_name }}',
                    'body' => "{{ employee_name }} has asked for leave and needs a decision.\n\nType: {{ leave_type }}\nFrom: {{ start_date }}\nTo: {{ end_date }}\nDays: {{ days }}\n\nReview the request: {{ url }}",
                ],
                'data' => ['employee_name', 'leave_type', 'start_date', 'end_date', 'days', 'url'],
            ],
            'leave.decided' => [
                'label' => 'Leave decided',
                'description' => 'A leave request was approved or declined. Tells the employee who asked.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Your leave request: {{ decision }}',
                    'body' => "Your leave request has been decided.\n\nDecision: {{ decision }}\nDecided by: {{ decided_by }}\nType: {{ leave_type }}\nFrom: {{ start_date }}\nTo: {{ end_date }}\n\nSee the request: {{ url }}",
                ],
                'data' => ['leave_type', 'start_date', 'end_date', 'decision', 'decided_by', 'url'],
            ],
        ],

        'auction' => [
            'auction.outbid' => [
                'label' => 'Outbid',
                'description' => 'Someone placed a higher bid. Tells the bidder who was outbid.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'You have been outbid on {{ auction_title }}',
                    'body' => "Another bidder is now ahead of you.\n\nAuction: {{ auction_title }}\nYour bid: {{ your_bid }} {{ currency }}\nCurrent price: {{ current_price }} {{ currency }}\nEnds: {{ ends_at }}\n\nSee the auction: {{ url }}",
                ],
                'data' => ['auction_title', 'your_bid', 'current_price', 'currency', 'ends_at', 'url'],
            ],
            'auction.won' => [
                'label' => 'Auction won',
                'description' => 'An auction closed with this person as the winning bidder. Tells the winner.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'You won {{ auction_title }}',
                    'body' => "You won an auction.\n\nAuction: {{ auction_title }}\nFinal price: {{ final_price }} {{ currency }}\n\nSee the auction: {{ url }}",
                ],
                'data' => ['auction_title', 'final_price', 'currency', 'url'],
            ],
            'auction.sold' => [
                'label' => 'Auction sold',
                'description' => 'An auction closed. Tells the seller how it ended.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Your auction has closed: {{ auction_title }}',
                    'body' => "Your auction has closed.\n\nAuction: {{ auction_title }}\nFinal price: {{ final_price }} {{ currency }}\nStatus: {{ status }}\n\nSee the auction: {{ url }}",
                ],
                'data' => ['auction_title', 'final_price', 'currency', 'status', 'url'],
            ],
            'auction.ending_soon' => [
                'label' => 'Auction ending soon',
                'description' => 'An auction is about to close.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Ending soon: {{ auction_title }}',
                    'body' => "An auction is about to close.\n\nAuction: {{ auction_title }}\nCurrent price: {{ current_price }} {{ currency }}\nEnds: {{ ends_at }}\n\nSee the auction: {{ url }}",
                ],
                'data' => ['auction_title', 'current_price', 'currency', 'ends_at', 'url'],
            ],
        ],

        'ehail' => [
            'ride.accepted' => [
                'label' => 'Ride accepted',
                'description' => 'A driver accepted a ride request. Tells the rider.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'A driver accepted your ride',
                    'body' => "A driver accepted your ride request.\n\nDriver: {{ driver_name }}\nVehicle: {{ vehicle }}\n\nSee your ride: {{ url }}",
                ],
                'data' => ['driver_name', 'vehicle', 'url'],
            ],
            'ride.cancelled' => [
                'label' => 'Ride cancelled',
                'description' => 'A ride was cancelled. Says who cancelled it and why.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Your ride was cancelled',
                    'body' => "A ride was cancelled.\n\nCancelled by: {{ cancelled_by_role }}\nReason: {{ reason }}\n\nSee the ride: {{ url }}",
                ],
                'data' => ['cancelled_by_role', 'reason', 'url'],
            ],
            'ride.expired' => [
                'label' => 'Ride request expired',
                'description' => 'No driver accepted a ride request in time. Tells the rider.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Your ride request expired',
                    'body' => "No driver accepted your ride request before it expired.\n\nSee the request: {{ url }}",
                ],
                'data' => ['url'],
            ],
            'driver.application_decided' => [
                'label' => 'Driver application decided',
                'description' => 'A fleet approved or declined a driver application. Tells the applicant.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Your driver application: {{ decision }}',
                    'body' => "Your driver application has been decided.\n\nDecision: {{ decision }}\nFleet: {{ fleet_name }}\nNote: {{ note }}\n\nSee your application: {{ url }}",
                ],
                'data' => ['decision', 'fleet_name', 'note', 'url'],
            ],
        ],

        'farms' => [
            'harvest.recorded' => [
                'label' => 'Harvest recorded',
                'description' => 'A harvest was recorded on a field.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Harvest recorded: {{ crop }} on {{ farm_name }}',
                    'body' => "A harvest was recorded.\n\nFarm: {{ farm_name }}\nField: {{ field_name }}\nCrop: {{ crop }}\nQuantity: {{ quantity }} {{ unit }}\nHarvested: {{ harvested_at }}\n\nSee the harvest: {{ url }}",
                ],
                'data' => ['farm_name', 'field_name', 'crop', 'quantity', 'unit', 'harvested_at', 'url'],
            ],
        ],

        'billing' => [
            // Data keys are exactly what Dot.Billing's NotifyClient sends (Dot.Billing PR #6).
            // Its link key is `invoice_url`, not `url`.
            'invoice.overdue' => [
                'label' => 'Invoice overdue',
                'description' => 'An invoice passed its due date unpaid. Tells the company owner.',
                'channel' => 'email',
                'recipient' => 'user',
                'notification_type' => 'transactional',
                'template' => [
                    'subject' => 'Invoice {{ invoice_number }} is overdue',
                    'body' => "An invoice on your account is overdue.\n\nCompany: {{ team_name }}\nInvoice: {{ invoice_number }}\nDue date: {{ due_date }}\nDays overdue: {{ days_overdue }}\nTotal: {{ total }}\nBalance due: {{ balance }}\n\nView the invoice: {{ invoice_url }}",
                ],
                'data' => ['team_name', 'invoice_number', 'total', 'balance', 'currency', 'due_date', 'days_overdue', 'invoice_url'],
            ],
        ],

    ],

];
