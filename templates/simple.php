<?php

// templates/simple.php

return ['name' => 'Simple Design', 'subject' => 'Email from {site_name}', 'content' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: Arial, sans-serif;"><div style="max-width: 600px; margin: 0 auto; background-color: #ffffff;"><div style="background-color: #ffffff; padding: 20px;"><h1 style="color: #333333; margin: 0;">{email_title}</h1></div><div style="background-color: #ffffff; padding: 20px;">{email_content}</div><div style="background-color: #f5f5f5; padding: 20px; font-size: 12px; color: #666666; text-align: center;">© {year} {site_name}. All rights reserved.</div></div></body></html>'];