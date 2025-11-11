from PIL import Image
import os

# Root folder containing your images
root_folder = "/home/relia/assets-automated/assets-cafe"

# Supported image formats
extensions = (".jpg", ".jpeg", ".png", ".gif")

# Walk through folder recursively
for subdir, dirs, files in os.walk(root_folder):
    for filename in files:
        if filename.lower().endswith(extensions):
            filepath = os.path.join(subdir, filename)
            try:
                # Get original file size
                original_size = os.path.getsize(filepath)

                img = Image.open(filepath)

                # Convert PNG/GIF with alpha to RGB first if needed
                if img.mode in ("RGBA", "P"):
                    img = img.convert("RGB")

                # Compress based on file type
                if filename.lower().endswith((".jpg", ".jpeg")):
                    img.save(filepath, quality=45, optimize=True)
                elif filename.lower().endswith(".png"):
                    img.save(filepath, optimize=True)
                elif filename.lower().endswith(".gif"):
                    img.save(filepath, optimize=True)

                # Get new file size
                new_size = os.path.getsize(filepath)

                print(f"{filepath}")
                print(f"  Before: {original_size / 1024:.2f} KB")
                print(f"  After : {new_size / 1024:.2f} KB")
                print(f"  Reduced: {100 - (new_size/original_size*100):.1f}%\n")

            except Exception as e:
                print(f"Skipping {filepath}, error: {e}")
