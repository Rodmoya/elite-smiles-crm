# Staff outreach cooldown clarification — September 23, 2026

Rod clarified that the 48-hour unanswered outreach cooldown applies only to automated Lead Agent follow-up, not to staff sending SMS or email. Staff activity remains part of the contact history used to determine the agent's next eligible attempt. Opt-outs and other contact restrictions remain enforced.

The production policy file is not tracked in main, and the working development checkout contains unrelated changes. The targeted workflow therefore downloads the reviewed live policy over verified FTPS, checks its exact SHA-256, changes only the cooldown condition to require `$automated`, runs behavioral PHP tests, saves and verifies a recoverable backup, and atomically replaces that single file. It fails if the live revision changes. The normal full-site deployment is excluded when this targeted option is selected.

The underlying policy still needs to be reconciled into the main repository along with its existing calling code before a full-site deployment can be assumed to reproduce production.
