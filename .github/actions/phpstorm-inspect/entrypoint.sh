#!/bin/sh -l

echo "$GITHUB_WORKSPACE/.idea"

# Create output directory
mkdir -p "$3"

# Only try to copy idea.properties if it exists
if [ -f "$GITHUB_WORKSPACE/idea.properties" ]; then
  cp "$GITHUB_WORKSPACE/idea.properties" /opt/ide/bin/
fi

# Setup inspection scope
if [ "$5" != "default" ]; then
  for file in phpstorm.vmoptions phpstorm64.vmoptions; do
    vmopts="/opt/ide/bin/$file"

    # Create file if missing
    if [ ! -f "$vmopts" ]; then
      echo "# Auto-generated $file" > "$vmopts"
    fi

    echo "-Didea.analyze.scope=$5" >> "$vmopts"
  done

  export STUDIO_VM_OPTIONS=/opt/ide/bin/phpstorm64.vmoptions
  export PHPSTORM_VM_OPTIONS=/opt/ide/bin/phpstorm64.vmoptions
fi

echo "Running inspections"
/opt/ide/bin/inspect.sh "$1" "$2" "$3" -d "$1" "-$4"

# Optional: check for output files
if [ ! -f "$3/.descriptions.xml" ]; then
  echo "No XML files generated in output dir. Possibly empty inspection run or broken profile."
  exit 1
fi