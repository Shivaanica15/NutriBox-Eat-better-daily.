(() => {
   const body = document.body;
   const toggles = document.querySelectorAll("[data-sidebar-toggle]");
   if (!toggles.length) {
      return;
   }

   const closeSidebar = () => body.classList.remove("admin-sidebar-open");
   const toggleSidebar = () =>
      body.classList.toggle("admin-sidebar-open");

   toggles.forEach((toggle) => {
      toggle.addEventListener("click", (event) => {
         event.preventDefault();
         toggleSidebar();
      });
   });

   document
      .querySelectorAll(".admin-sidebar .nav-link")
      .forEach((link) => {
         link.addEventListener("click", () => {
            if (window.innerWidth <= 991) {
               closeSidebar();
            }
         });
      });

   window.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
         closeSidebar();
      }
   });
})();
