
<script>
export default {
    data() {
        return {
            message: 'Hello, World!'
        }
    }
}
</script>

<template>
    <div style="background-color: lightblue;">
        <h1>Welcome to the Test Mart</h1>
        <AppAlert>This is the main landing page of the application.</AppAlert>
        <button @click="message += '!'">Hello "!"</button>
        <p>{{ message }}</p>
    </div>
</template>