<x-error-page
    :code="$exception->getStatusCode()"
    label="Something went wrong"
    title="Off"
    highlight="course."
    message="That request didn’t go as planned. Head back to the start and try again."
/>
