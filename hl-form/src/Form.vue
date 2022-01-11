<script>
export default {
  props: {
    labelWidth: {
      type: String,
      default: "auto",
    },
  },
  data() {
    return {
      labelPosition: "top",
    };
  },
  render(h) {
    this.labelPosition = window.innerWidth < 768 ? "top" : "left";
    window.addEventListener("resize", () => {
      this.labelPosition = window.innerWidth < 768 ? "top" : "left";
    });
    return h(
      "el-form",
      {
        props: {
          labelPosition: this.labelPosition,
          labelWidth: this.labelWidth,
        },
        on: this.$listeners,
        ref: "form",
        attrs: this.$attrs,
      },
      this.$slots.default
    );
  },
  methods: {
    validate(callback) {
      return this.$refs.form.validate(callback);
    },
    resetFields() {
      return this.$refs.form.resetFields();
    },
    clearValidate() {
      return this.$refs.form.clearValidate(arguments[0]);
    },
    validateField() {
      return this.$refs.form.validateField(arguments[0]);
    },
  },
};
</script>