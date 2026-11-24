# Ask core for plans while competencies are disabled.
s/if \(!api::is_enabled\(\)\) \{/if (false) {/;
