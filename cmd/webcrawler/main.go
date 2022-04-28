package main

import (
	"flag"
	"fmt"
	"github.com/nakamura-akifumi/kassis"
	"os"
)

var Cfg kassiscore.KENVCONF

func main() {
	fmt.Printf("kassis webcrawler. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	flag.Parse()

	Cfg := kassiscore.LoadConfig()

	fmt.Println(Cfg.ExtDir)

	if len(flag.Args()) != 1 {
		fmt.Println("Error: no uri (example: https://wiki.example.com/operationguide")
		os.Exit(1)
	}

	uri := flag.Arg(0)

	fmt.Print(uri)
	fmt.Print("\n")

	kassiscore.WebCrawler(uri, Cfg)
}
