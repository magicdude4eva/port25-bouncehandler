Bounce provider plugins should be placed in this directory and then loaded via `setup.php`

# feedback-loop-processor.php
Still work-in-progress and I anticipate more changes in it as we receive FBL reports. For now I suggest that you go through the `feedback-loop-processor.php` and adjust settings. In the near future, I will move settings to `setup.php`.

FBLs tested against:
- Microsoft JMRP (arf)
- OpenSRS (arf via ReturnPath)
- Yahoo!-Mail-Feedback/2.0 (arf)

### Microsoft Junkmail Reporting Program
You can register via https://postmaster.live.com/snds/JMRP.aspx. There is misleading information floating around that the ARF format is incorrect. I have tested this and Microsoft provides all the necessary fields for FBL processing.

I did notice that JMRP does not provide the agent information and in certain cases submits the email address within `<`/`>`. The `feedback-loop-processor.php` takes care of this and santises missing/wrong information.

### OpenSRS
I have only received one OpenSRS FBL so far. OpenSRS/Returnpath anonymises the recipient email address. I have worked around this by extracting List-ID/Subscriber-ID from the header and writing it to the FBL record.

### Yahoo!-Mail-Feedback
This works like a dream - no issues and all arf fields are filled.

# PHP Dependencies
The only requirement should be `php-PHPMailer` 

