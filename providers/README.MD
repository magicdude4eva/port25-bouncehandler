Bounce provider plugins should be placed in this directory and then loaded via `setup.php`

## feedback-loop-processor.php
Still work-in-progress and I anticipate more changes in it as we receive FBL reports. For now I suggest that you go through the `feedback-loop-processor.php` and adjust settings. In the near future, I will move settings to `setup.php`.

FBLs tested against:
- Yahoo!-Mail-Feedback/2.0 (arf)
- Microsoft JMRP (arf)

## Microsoft Junkmail Reporting Program
You can register via https://postmaster.live.com/snds/JMRP.aspx. There is misleading information floating around that the ARF format is incorrect. I have tested this and Microsoft provides all the necessary fields for FBL processing.

I did notice that JMRP does not provide the agent information and in certain cases submits the email address within `<`/`>`. The `feedback-loop-processor.php` takes care of this and santises missing/wrong information.


