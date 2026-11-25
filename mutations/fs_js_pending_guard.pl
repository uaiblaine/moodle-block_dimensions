# Drop the pending guard: a second press sends a second toggle before the first answer.
s/if \(button\.dataset\.favPending\) \{/if (false) {/;
