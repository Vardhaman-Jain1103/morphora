# Shrinks large images in php-site/media/ (run after adding new photos there).
#   powershell -ExecutionPolicy Bypass -File new_version/_build/optimize-images.ps1
# Any JPEG/PNG over 600 KB is resized to at most 1600px wide; JPEGs are re-saved at quality 82.
Add-Type -AssemblyName System.Drawing
$media = Join-Path $PSScriptRoot '..\..\php-site\media'
$maxW = 1600
$jpegCodec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
$params = New-Object System.Drawing.Imaging.EncoderParameters 1
$params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter ([System.Drawing.Imaging.Encoder]::Quality), 82L
$before = 0; $after = 0; $count = 0
Get-ChildItem $media -Recurse -File -Include *.jpg, *.jpeg, *.png | Where-Object { $_.Length -gt 600KB } | ForEach-Object {
  $f = $_.FullName; $before += $_.Length
  $img = [System.Drawing.Image]::FromFile($f)
  $w = [Math]::Min($maxW, $img.Width); $h = [int]($img.Height * $w / $img.Width)
  $bmp = New-Object System.Drawing.Bitmap $w, $h
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.DrawImage($img, 0, 0, $w, $h); $g.Dispose(); $img.Dispose()
  $tmp = "$f.tmp"
  if ($_.Extension -eq '.png') { $bmp.Save($tmp, [System.Drawing.Imaging.ImageFormat]::Png) } else { $bmp.Save($tmp, $jpegCodec, $params) }
  $bmp.Dispose()
  Move-Item $tmp $f -Force
  $after += (Get-Item $f).Length; $count++
}
"Optimised $count images: {0:N1} MB -> {1:N1} MB" -f ($before / 1MB), ($after / 1MB)
