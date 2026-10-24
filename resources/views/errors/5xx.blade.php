<x-error-page
    :code="$exception->getStatusCode()"
    label="Server error"
    title="We hit the"
    highlight="wall."
    message="Something went wrong on our side. Give us a moment to recover, then try again."
/>
