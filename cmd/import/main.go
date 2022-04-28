package main

import (
	"flag"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"

	"github.com/nakamura-akifumi/kassis"
)

func main() {

	fmt.Printf("kassis document importer. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")
	/*
		var (
			targetfilename = flag.String("f", "target filename or foldername.", "Message")
		)
	*/
	flag.Parse()

	if len(flag.Args()) != 1 {
		fmt.Println("Error: no filepath or directory")
		os.Exit(1)
	}

	files := []string{}

	if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || f.IsDir() {
		if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || !f.IsDir() {
			fmt.Printf("Error: No such file or directory (%s)\n", flag.Arg(0))
			os.Exit(2)
		} else {

			err := filepath.WalkDir(flag.Arg(0), func(path string, info fs.DirEntry, err error) error {
				if err != nil {
					fmt.Println("Error: failed filepath.WalkDir")
					fmt.Println(err)
					os.Exit(3)
				}

				if info.IsDir() {
					return nil
				}

				files = append(files, path)
				return nil
			})
			if err != nil {
				fmt.Println("Error: failed filepath.WalkDir")
				fmt.Println(err)
				os.Exit(3)
			}
		}
	} else {
		// TODO: fullpath にしても良いかな。
		files = append(files, flag.Arg(0))
	}

	fmt.Print(files)
	fmt.Print("\n")

	err := kassiscore.ImportFromFile(files)
	if err != nil {
		fmt.Println(err)
		os.Exit(10)
	}
}
